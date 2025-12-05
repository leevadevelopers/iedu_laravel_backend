<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\School\SchoolUser;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Http\Requests\Academic\StoreTeacherRequest;
use App\Http\Requests\Academic\UpdateTeacherRequest;
use App\Services\V1\Academic\TeacherService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{
    use ApiResponseTrait;
    protected TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try getCurrentSchool method first (preferred)
        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        // Fallback to school_id attribute
        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        // Try activeSchools relationship
        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
    }

    /**
     * Get the current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Display a listing of teachers
     */
    public function index(Request $request): JsonResponse
    {
        $teachers = $this->teacherService->getTeachers($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $teachers->items(),
            'meta' => [
                'total' => $teachers->total(),
                'per_page' => $teachers->perPage(),
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created teacher
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        // Get school_id from user if not provided
        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not associated with any school'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $userId = null;

            // If user_id is provided, use existing user_id
            if ($request->has('user_id') && $request->user_id) {
                $userId = $request->user_id;
            } else {
                // If user_id is not provided, create new user automatically
                // Use user_data if provided, otherwise use teacher data
                $userData = $request->user_data ?? [];
                $identifier = $userData['email'] ?? $userData['phone'] ?? $request->email ?? $request->phone;
                $userType = isset($userData['email']) || isset($request->email) ? 'email' : 'phone';
                $userName = $userData['name'] ?? "{$request->first_name} {$request->last_name}";

                if (!$identifier) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Email or phone is required to create user account'
                    ], 422);
                }

                // Check if user with this identifier already exists
                $existingUser = User::where('identifier', $identifier)->first();
                if ($existingUser) {
                    $userId = $existingUser->id;
                } else {
                    // Create new user with default password
                    $newUser = User::create([
                        'name' => $userName,
                        'identifier' => $identifier,
                        'type' => $userType,
                        'password' => bcrypt('@ProfessorIedu'),
                        'must_change' => true,
                        'tenant_id' => $tenantId,
                        'school_id' => $schoolId,
                        'is_active' => true,
                        'user_type' => 'teacher',
                    ]);

                    $userId = $newUser->id;

                    // Create TenantUser association
                    $newUser->tenants()->attach($tenantId, [
                        'status' => 'active',
                        'joined_at' => now(),
                        'role_id' => Role::where('name', 'teacher')->first()?->id,
                        'current_tenant' => true,
                    ]);
                }
            }

            // Ensure user_id is set
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create or retrieve user. Email or phone is required.'
                ], 422);
            }

            // Create teacher
            $teacherData = $request->validated();
            $teacherData['tenant_id'] = $tenantId;
            $teacherData['school_id'] = $schoolId;
            $teacherData['user_id'] = $userId;

            $teacher = $this->teacherService->createTeacher($teacherData);

            // Create or update SchoolUser association (idempotent)
            if ($teacher->user_id && $teacher->school_id) {
                SchoolUser::updateOrCreate(
                    [
                        'school_id' => $teacher->school_id,
                        'user_id'   => $teacher->user_id,
                    ],
                    [
                        'role'        => 'teacher',
                        'status'      => 'active',
                        'start_date'  => now(),
                        'permissions' => $this->getDefaultTeacherPermissions(),
                    ]
                );
            }

            DB::commit();

            return $this->successResponse($teacher, 'Teacher created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified teacher
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Find teacher explicitly to avoid model binding issues with TenantScope
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $teacher->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this teacher'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $teacher->load(['user', 'classes.subject', 'classes.academicYear'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified teacher
     */
    public function update(UpdateTeacherRequest $request, $id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $teacher->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this teacher'
                ], 403);
            }

            $updatedTeacher = $this->teacherService->updateTeacher($teacher, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher updated successfully',
                'data' => $updatedTeacher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified teacher
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $teacher->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this teacher'
                ], 403);
            }

            $this->teacherService->deleteTeacher($teacher);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher terminated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to terminate teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teachers by department
     */
    public function byDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'department' => 'required|string|max:100'
        ]);

        $teachers = $this->teacherService->getTeachersByDepartment($request->department);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by employment type
     */
    public function byEmploymentType(Request $request): JsonResponse
    {
        $request->validate([
            'employment_type' => 'required|in:full_time,part_time,substitute,contract,volunteer'
        ]);

        $teachers = $this->teacherService->getTeachersByEmploymentType($request->employment_type);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by specialization
     */
    public function bySpecialization(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersBySpecialization($request->specialization);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teachers by grade level
     */
    public function byGradeLevel(Request $request): JsonResponse
    {
        $request->validate([
            'grade_level' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersByGradeLevel($request->grade_level);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Search teachers
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2'
        ]);

        $teachers = $this->teacherService->searchTeachers($request->search);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Get teacher workload
     */
    public function workload($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $workload = $this->teacherService->getTeacherWorkload($teacher);

            return $this->successResponse($workload);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher workload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher's classes
     */
    public function classes($id, Request $request): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $classes = $this->teacherService->getTeacherClasses($teacher, $request->all());

            return $this->successResponse($classes);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher statistics
     */
    public function statistics($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $statistics = $this->teacherService->getTeacherStatistics($teacher);

            return $this->successResponse($statistics);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update teacher schedule
     */
    public function updateSchedule($id, Request $request): JsonResponse
    {
        $request->validate([
            'schedule' => 'required|array',
            'schedule.*' => 'array',
            'schedule.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule.*.available_times' => 'required|array',
            'schedule.*.available_times.*' => [
                'required',
                'string',
                'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9](-[01]?[0-9]|2[0-3]:[0-5][0-9])?$/'
            ],
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $updatedTeacher = $this->teacherService->updateTeacherSchedule($teacher, $request->schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher schedule updated successfully',
                'data' => $updatedTeacher
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check teacher availability
     */
    public function checkAvailability($id, Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => ['required', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $isAvailable = $this->teacherService->checkTeacherAvailability(
                $teacher,
                $request->day,
                $request->time
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'teacher_id' => $teacher->id,
                    'day' => $request->day,
                    'time' => $request->time,
                    'available' => $isAvailable
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check teacher availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available teachers at specific time
     */
    public function availableAt(Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        $teachers = $this->teacherService->getAvailableTeachers($request->day, $request->time);

        return $this->successPaginatedResponse($teachers);
    }

    /**
     * Assign teacher to class
     */
    public function assignToClass($id, Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id'
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $this->teacherService->assignTeacherToClass($teacher, $request->class_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher assigned to class successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign teacher to class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teacher performance metrics
     */
    public function performanceMetrics($id, Request $request): JsonResponse
    {
        $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id'
        ]);

        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $metrics = $this->teacherService->getTeacherPerformanceMetrics(
                $teacher,
                $request->academic_term_id
            );

            return $this->successResponse($metrics);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher dashboard data
     */
    public function dashboard($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            $workload = $this->teacherService->getTeacherWorkload($teacher);
            $classes = $this->teacherService->getTeacherClasses($teacher);
            $statistics = $this->teacherService->getTeacherStatistics($teacher);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'teacher' => $teacher,
                    'workload' => $workload,
                    'classes' => $classes,
                    'statistics' => $statistics
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get teacher dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teachers for class assignment
     */
    public function forClassAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'grade_level' => 'required|string'
        ]);

        // Get teachers by specialization and grade level separately
        $specializationTeachers = $this->teacherService->getTeachersBySpecialization($request->subject_id);
        $gradeLevelTeachers = $this->teacherService->getTeachersByGradeLevel($request->grade_level);

        // Combine and deduplicate the results
        $allTeachers = $specializationTeachers->getCollection()
            ->merge($gradeLevelTeachers->getCollection())
            ->unique('id');

        // Create a new paginator with the combined results
        $combinedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $allTeachers,
            $allTeachers->count(),
            $specializationTeachers->perPage(),
            $specializationTeachers->currentPage(),
            [
                'path' => $specializationTeachers->path(),
                'pageName' => 'page',
            ]
        );

        return $this->successPaginatedResponse($combinedPaginator);
    }

    /**
     * Create a draft teacher (with minimal validation)
     */
    public function createDraft(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Minimal validation for draft - only require first_name and last_name
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'nullable|string|max:50|unique:teachers,employee_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate employee_id if not provided
            $employeeId = $request->employee_id ?? $this->generateEmployeeId($schoolId);

            // Create draft teacher with status='draft'
            $teacherData = $request->only([
                'first_name', 'middle_name', 'last_name', 'preferred_name',
                'email', 'phone', 'employee_id', 'employment_type', 'hire_date'
            ]);
            $teacherData['tenant_id'] = $tenantId;
            $teacherData['school_id'] = $schoolId;
            $teacherData['employee_id'] = $employeeId;
            $teacherData['status'] = 'draft';
            $teacherData['employment_type'] = $teacherData['employment_type'] ?? 'full_time';
            $teacherData['hire_date'] = $teacherData['hire_date'] ?? now();

            $teacher = Teacher::create($teacherData);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Draft teacher created successfully',
                'data' => $teacher
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create draft teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a draft teacher (change status from draft to active)
     */
    public function publish($id): JsonResponse
    {
        try {
            $teacher = $this->teacherService->getTeacherById($id);

            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $teacher->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this teacher'
                ], 403);
            }

            // Check if teacher is a draft
            if ($teacher->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher is not a draft. Only draft teachers can be published.'
                ], 422);
            }

            // Validate required fields before publishing
            $requiredFields = ['first_name', 'last_name', 'employee_id', 'hire_date'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($teacher->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot publish teacher. Missing required fields: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields
                ], 422);
            }

            // Update status to active
            $teacher->update(['status' => 'active']);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher published successfully',
                'data' => $teacher->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate teacher assignment to subject/class
     */
    public function validateAssignment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $teacher = Teacher::findOrFail($request->teacher_id);
            $subject = \App\Models\V1\Academic\Subject::findOrFail($request->subject_id);

            $errors = [];
            $warnings = [];
            $valid = true;

            // Verify access
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $teacher->school_id != $userSchoolId || $subject->school_id != $userSchoolId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to these resources'
                ], 403);
            }

            // Check 1: Teacher is active
            if ($teacher->status !== 'active') {
                $errors[] = 'Teacher is not active';
                $valid = false;
            }

            // Check 2: Teacher has subject specialization
            $specializations = $teacher->specializations_json ?? [];
            $hasSpecialization = false;
            if (is_array($specializations)) {
                // Check if subject_id or subject name is in specializations
                foreach ($specializations as $spec) {
                    if (is_array($spec) && isset($spec['subject_id']) && $spec['subject_id'] == $subject->id) {
                        $hasSpecialization = true;
                        break;
                    } elseif (is_string($spec) && $spec == $subject->name) {
                        $hasSpecialization = true;
                        break;
                    }
                }
            }

            if (!$hasSpecialization) {
                $warnings[] = "Teacher may not have specialization in subject: {$subject->name}";
            }

            // Check 3: Teacher has no conflicting schedules (if class_id provided)
            if ($request->class_id) {
                $class = \App\Models\V1\Academic\AcademicClass::findOrFail($request->class_id);
                // This would require checking Schedule model - simplified for now
                $warnings[] = 'Schedule conflict check recommended';
            }

            return response()->json([
                'status' => 'success',
                'valid' => $valid,
                'errors' => $errors,
                'warnings' => $warnings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to validate assignment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique employee ID
     */
    private function generateEmployeeId(int $schoolId): string
    {
        $prefix = 'EMP';
        $year = date('Y');
        $lastEmployee = Teacher::where('school_id', $schoolId)
            ->where('employee_id', 'like', "{$prefix}{$year}%")
            ->orderBy('employee_id', 'desc')
            ->first();

        if ($lastEmployee && preg_match('/\d+$/', $lastEmployee->employee_id, $matches)) {
            $nextNumber = intval($matches[0]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s%s%04d', $prefix, $year, $nextNumber);
    }

    /**
     * Get default permissions for teachers
     */
    private function getDefaultTeacherPermissions(): array
    {
        return [
            'view_students',
            'view_classes',
            'view_grades',
            'create_grades',
            'update_grades',
            'view_attendance',
            'create_attendance',
            'update_attendance',
            'view_schedule',
            'view_assignments',
            'create_assignments',
            'update_assignments',
            'view_announcements',
            'create_announcements'
        ];
    }
}

<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\AcademicClass;
use App\Http\Requests\Academic\StoreAcademicClassRequest;
use App\Http\Requests\Academic\UpdateAcademicClassRequest;
use App\Services\V1\Academic\AcademicClassService;
use App\Traits\ApiResponseTrait;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicClassController extends Controller
{
    use ApiResponseTrait;
    protected AcademicClassService $classService;

    public function __construct(AcademicClassService $classService)
    {
        $this->classService = $classService;
        $this->middleware('permission:academic.classes.view')->only(['index', 'show', 'roster', 'teacherClasses']);
        $this->middleware('permission:academic.classes.create')->only(['store']);
        $this->middleware('permission:academic.classes.edit')->only(['update']);
        $this->middleware('permission:academic.classes.delete')->only(['destroy']);
        $this->middleware('permission:academic.classes.enroll')->only(['enrollStudent', 'removeStudent']);
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
     * Display a listing of classes
     */
    public function index(Request $request): JsonResponse
    {

        $filters = $request->all();

        // If grouped by grade_level, return grouped data (no pagination)
        if (isset($filters['group_by']) && $filters['group_by'] === 'grade_level') {
            $data = $this->classService->getClassesGrouped($filters);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        }

        $classes = $this->classService->getClasses($filters);

        return response()->json([
            'status' => 'success',
            'data' => $classes->items(),
            'meta' => [
                'total' => $classes->total(),
                'per_page' => $classes->perPage(),
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created class
     */
    public function store(StoreAcademicClassRequest $request): JsonResponse
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

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['tenant_id'] = $tenantId;
            $data['school_id'] = $this->getCurrentSchoolId();

            $class = $this->classService->createClass($data);

            DB::commit();

            return $this->successResponse($class, 'Class created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified class
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Find class explicitly to avoid model binding issues with TenantScope
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $class->load([
                    'subject', 'primaryTeacher', 'students', 'academicYear', 'academicTerm'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified class
     */
    public function update(UpdateAcademicClassRequest $request, $id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            DB::beginTransaction();

            $updatedClass = $this->classService->updateClass($class, $request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Class updated successfully',
                'data' => $updatedClass
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified class
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $class = $this->classService->getClassById($id);

            if (!$class) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            $this->classService->deleteClass($class);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Class deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Enroll student in class
     */
    public function enrollStudent($id, Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            DB::beginTransaction();

            $class = $this->classService->getClassById($id);

            if (!$class) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            $enrollment = $this->classService->enrollStudent($class, $request->student_id, [
                'grade_level_at_enrollment' => $request->grade_level_at_enrollment,
                'academic_year_id' => $request->academic_year_id,
                'enrollment_date' => $request->enrollment_date,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student enrolled successfully',
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enroll student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove student from class
     */
    public function removeStudent($id, Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            DB::beginTransaction();

            $class = $this->classService->getClassById($id);

            if (!$class) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            $this->classService->removeStudent($class, $request->student_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student removed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get class roster
     */
    public function roster($id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            $roster = $this->classService->getClassRoster($class);

            return $this->successResponse($roster);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get class roster',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher's classes
     */
    public function teacherClasses(Request $request): JsonResponse
    {
        $teacherId = $request->get('teacher_id', Auth::id());
        $classes = $this->classService->getTeacherClasses($teacherId, $request->all());

        return $this->successResponse($classes);
    }

    /**
     * Create a draft class (with minimal validation)
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

        // Minimal validation for draft - only require name, subject_id, academic_year_id
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level' => 'nullable|string|max:20',
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

            // Create draft class with status='draft'
            $classData = $request->only([
                'name', 'section', 'class_code', 'subject_id', 'academic_year_id',
                'academic_term_id', 'grade_level', 'max_students', 'primary_teacher_id'
            ]);
            $classData['tenant_id'] = $tenantId;
            $classData['school_id'] = $schoolId;
            $classData['status'] = 'draft';
            $classData['current_enrollment'] = 0;
            $classData['max_students'] = $classData['max_students'] ?? 30;

            $class = AcademicClass::create($classData);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Draft class created successfully',
                'data' => $class
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create draft class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a draft class (change status from draft to active)
     */
    public function publish($id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $class->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this class'
                ], 403);
            }

            // Check if class is a draft
            if ($class->status !== 'draft') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class is not a draft. Only draft classes can be published.'
                ], 422);
            }

            // Validate required fields before publishing
            $requiredFields = ['name', 'subject_id', 'academic_year_id', 'grade_level'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($class->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot publish class. Missing required fields: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields
                ], 422);
            }

            // Update status to active
            $class->update(['status' => 'active']);

            return response()->json([
                'status' => 'success',
                'message' => 'Class published successfully',
                'data' => $class->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish class',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

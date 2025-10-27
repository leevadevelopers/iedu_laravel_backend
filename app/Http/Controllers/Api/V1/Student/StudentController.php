<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Constants\ErrorCodes;
use App\Http\Helpers\ApiResponse;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\SchoolUser;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Get authenticated user
     */
    protected function getAuthenticatedUser()
    {
        $user = auth('api')->user();
        if (!$user) {
            throw new \Exception('User not authenticated');
        }
        return $user;
    }

    /**
     * Validate user has access to school
     */
    protected function validateUserSchoolAccess(int $schoolId): bool
    {
        try {
            $user = $this->getAuthenticatedUser();

            return DB::table('school_users')
                ->where('user_id', $user->id)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current tenant ID
     */
    protected function getTenantId(): int
    {
        try {
            $user = $this->getAuthenticatedUser();
            $tenantId = $user->tenant_id ?? session('tenant_id');

            if (!$tenantId) {
                $userTenantId = $user->tenant_id;
                if ($userTenantId) {
                    $tenantId = $userTenantId;
                    session(['tenant_id' => $tenantId]);
                } else {
                    throw new \Exception('No tenant context available');
                }
            }

            return $tenantId;
        } catch (\Exception $e) {
            throw new \Exception('Failed to get tenant ID: ' . $e->getMessage());
        }
    }

        /**
     * Create user account for student automatically
     */
    protected function createUserForStudent(array $studentData): \App\Models\User
    {
        // Validate required fields
        if (empty($studentData['email'])) {
            throw new \Exception('Email is required to create user account');
        }

        // Check if user with this email already exists
        $existingUser = \App\Models\User::where('identifier', $studentData['email'])
            ->orWhere('identifier', strtolower($studentData['email']))
            ->first();

        if ($existingUser) {
            throw new \Exception('A user with this email already exists');
        }

        // Get tenant ID
        $tenantId = $this->getTenantId();

        // Generate random password
        $password = Str::random(12);

        // Create user account (no school_id or role_id in users table)
        $user = \App\Models\User::create([
            'tenant_id' => $tenantId,
            'name' => trim(($studentData['first_name'] ?? '') . ' ' . ($studentData['last_name'] ?? '')),
            'identifier' => strtolower($studentData['email']), // Use email as identifier (lowercase)
            'type' => 'email',
            'school_id' => $studentData['school_id'],
            'role_id' => $studentData['role_id'],
            'password' => Hash::make($password),
            'must_change' => true, // Force password change on first login
        ]);

        // Assign role with tenant context by directly inserting into model_has_roles
        try {
            $role = \Spatie\Permission\Models\Role::where('name', 'student')->first();
            if (!$role) {
                Log::error('Student role not found in database');
            } else {
                DB::table('model_has_roles')->insert([
                    'role_id' => $role->id,
                    'model_type' => \App\Models\User::class,
                    'model_id' => $user->id,
                    'tenant_id' => $tenantId
                ]);
                Log::info('Assigned student role to user', [
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'tenant_id' => $tenantId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to assign role to user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw - role assignment failure shouldn't prevent user creation
        }

        // Send welcome email with temporary password
        try {
            $user->notify(new \App\Notifications\StudentAccountCreated($password));
        } catch (\Exception $e) {
            Log::warning('Failed to send welcome email to student', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        Log::info('Auto-created user for student', [
            'user_id' => $user->id,
            'student_email' => $studentData['email']
        ]);

        return $user;
    }

    /**
     * Display a listing of students with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get authenticated user and tenant
            $user = $this->getAuthenticatedUser();
            $tenantId = $this->getTenantId();

            $query = Student::with([
                'enrollmentHistory',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear'
            ]);

            // Apply filters
            if ($request->has('enrollment_status')) {
                $query->where('enrollment_status', $request->enrollment_status);
            }

            if ($request->has('current_grade_level')) {
                $query->where('current_grade_level', $request->current_grade_level);
            }

            if ($request->has('school_id')) {
                $schoolId = $request->school_id;

                // Validate user has access to this school
                if (!$this->validateUserSchoolAccess($schoolId)) {
                    return ApiResponse::error(
                        'You do not have access to this school',
                        ErrorCodes::SCHOOL_ACCESS_DENIED,
                        null,
                        403
                    );
                }

                $query->where('school_id', $schoolId);
            }

            if ($request->has('current_academic_year_id')) {
                $query->where('current_academic_year_id', $request->current_academic_year_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $students = $query->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($request->get('per_page', 15));

            return ApiResponse::paginated($students);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve students', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error(
                'Failed to retrieve students',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Store a newly created student with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'user_id' => 'nullable|exists:users,id',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'birth_place' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'required|email|max:255|unique:users,identifier',
            'phone' => 'required|string|max:20',
            'address_json' => 'required|array',
            'school_id' => 'required|exists:schools,id',
            'current_academic_year_id' => 'required|exists:academic_years,id',
            'current_grade_level' => 'required|string|max:20',
            'admission_date' => 'required|date',
            'enrollment_status' => 'required|in:enrolled,transferred,graduated,withdrawn,suspended',
            'expected_graduation_date' => 'nullable|date|after:admission_date',
            'learning_profile_json' => 'nullable|array',
            'accommodation_needs_json' => 'nullable|array',
            'language_profile_json' => 'nullable|array',
            'medical_information_json' => 'nullable|array',
            'emergency_contacts_json' => 'nullable|array',
            'special_circumstances_json' => 'nullable|array',
            'current_gpa' => 'nullable|numeric|min:0|max:4',
            'attendance_rate' => 'nullable|numeric|min:0|max:100',
            'behavioral_points' => 'nullable|integer|min:0',
            'form_data' => 'nullable|array', // For Form Engine integration
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Validation failed',
                ErrorCodes::VALIDATION_FAILED,
                $validator->errors(),
                422
            );
        }

        try {
            DB::beginTransaction();

            // Get authenticated user and tenant
            $user = $this->getAuthenticatedUser();
            $tenantId = $this->getTenantId();

            // Validate school access
            if (!$this->validateUserSchoolAccess($request->school_id)) {
                return ApiResponse::error(
                    'You do not have access to this school',
                    ErrorCodes::SCHOOL_ACCESS_DENIED,
                    null,
                    403
                );
            }

            // Auto-create user if not provided
            $userId = $request->user_id;
            if (!$userId) {
                if (!$request->email) {
                    return ApiResponse::error(
                        'Email is required to create user account',
                        ErrorCodes::VALIDATION_FAILED,
                        ['email' => ['Email is required when user_id is not provided']],
                        422
                    );
                }

                try {
                    $newUser = $this->createUserForStudent([
                        'tenant_id' => $tenantId,
                        'first_name' => $request->first_name,
                        'role_id' => 4,
                        'school_id' => $request->school_id,
                        'last_name' => $request->last_name,
                        'email' => $request->email,
                    ]);
                    $userId = $newUser->id;
                } catch (\Exception $e) {
                    Log::error('Failed to auto-create user for student', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return ApiResponse::error(
                        'Failed to create user account: ' . $e->getMessage(),
                        ErrorCodes::OPERATION_FAILED,
                        ['error_details' => $e->getMessage()],
                        500
                    );
                }
            }

            // Create student
            $studentData = $request->except(['form_data', 'family_relationships', 'tenant_id', 'user_id']);
            $studentData['tenant_id'] = $tenantId;
            $studentData['user_id'] = $userId;

            $studentData['student_number'] = $this->generateStudentNumber();

            // Set default values
            $studentData['behavioral_points'] = $studentData['behavioral_points'] ?? 0;

            $student = Student::create($studentData);

            // Create SchoolUser association
            if ($student->user_id && $student->school_id) {
                SchoolUser::create([
                    'school_id' => $student->school_id,
                    'user_id' => $student->user_id,
                    'role' => 'student',
                    'status' => 'active',
                    'start_date' => now(),
                    'permissions' => $this->getDefaultStudentPermissions()
                ]);
            }

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('student_registration', $request->form_data);
                $this->formEngineService->createFormInstance('student_registration', $processedData, 'Student', $student->id, $tenantId);
            }

            // Start enrollment workflow
            $workflow = $this->workflowService->startWorkflow($student, 'student_enrollment', [
                'steps' => [
                    ['step_number' => 1, 'step_name' => 'document_verification', 'step_type' => 'verification', 'required_role' => 'admin', 'status' => 'pending'],
                    ['step_number' => 2, 'step_name' => 'parent_consent', 'step_type' => 'approval', 'required_role' => 'parent', 'status' => 'pending'],
                    ['step_number' => 3, 'step_name' => 'medical_assessment', 'step_type' => 'assessment', 'required_role' => 'nurse', 'status' => 'pending'],
                    ['step_number' => 4, 'step_name' => 'final_approval', 'step_type' => 'approval', 'required_role' => 'admin', 'status' => 'pending']
                ]
            ]);

            DB::commit();

            return ApiResponse::created([
                'student' => $student->load(['school', 'currentAcademicYear']),
                'workflow_id' => $workflow->id,
                'student_number' => $student->student_number
            ], 'Student created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create student', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error(
                'Failed to create student',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Display the specified student
     */
    public function show(Student $student): JsonResponse
    {
        try {
            // Validate school access
            if (!$this->validateUserSchoolAccess($student->school_id)) {
                return ApiResponse::error(
                    'You do not have access to this student',
                    ErrorCodes::SCHOOL_ACCESS_DENIED,
                    null,
                    403
                );
            }

            $student->load([
                'enrollmentHistory',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear'
            ]);

            return ApiResponse::success($student);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve student', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error(
                'Failed to retrieve student',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Update the specified student
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'user_id' => 'sometimes|exists:users,id',
            'first_name' => 'sometimes|required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'date_of_birth' => 'sometimes|required|date|before:today',
            'birth_place' => 'nullable|string|max:255',
            'gender' => 'sometimes|required|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'sometimes|required|email|max:255|unique:students,email,' . $student->id,
            'phone' => 'nullable|string|max:20',
            'address_json' => 'nullable|array',
            'school_id' => 'sometimes|required|exists:schools,id',
            'current_academic_year_id' => 'sometimes|required|exists:academic_years,id',
            'current_grade_level' => 'sometimes|required|string|max:20',
            'admission_date' => 'sometimes|required|date',
            'enrollment_status' => 'sometimes|required|in:enrolled,transferred,graduated,withdrawn,suspended',
            'expected_graduation_date' => 'nullable|date|after:admission_date',
            'learning_profile_json' => 'nullable|array',
            'accommodation_needs_json' => 'nullable|array',
            'language_profile_json' => 'nullable|array',
            'medical_information_json' => 'nullable|array',
            'emergency_contacts_json' => 'nullable|array',
            'special_circumstances_json' => 'nullable|array',
            'current_gpa' => 'nullable|numeric|min:0|max:4',
            'attendance_rate' => 'nullable|numeric|min:0|max:100',
            'behavioral_points' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $student->update($request->all());

            // If enrollment status changed, create enrollment history
            if ($request->has('enrollment_status') && $request->enrollment_status !== $student->getOriginal('enrollment_status')) {
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => $request->enrollment_status,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'reason' => $request->get('status_change_reason'),
                    'tenant_id' => $student->tenant_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => $student->fresh()->load(['school', 'currentAcademicYear'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified student
     */
    public function destroy(Student $student): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Soft delete student
            $student->delete();

            // Archive related records
            $student->enrollmentHistory()->update(['status' => 'archived']);
            $student->documents()->update(['status' => 'archived']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get student academic summary
     */
    public function academicSummary(Student $student): JsonResponse
    {
        try {
            $summary = [
                'student' => $student->only(['id', 'first_name', 'last_name', 'student_number', 'current_grade_level']),
                'current_enrollment' => $student->enrollmentHistory()->latest()->first(),
                'academic_progress' => $this->getAcademicProgress($student),
                'attendance_summary' => $this->getAttendanceSummary($student),
                'documents_status' => $this->getDocumentsStatus($student),
                'family_relationships' => $student->familyRelationships()->with('relatedPerson')->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer student to another school
     */
    public function transfer(Request $request, Student $student): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_school_id' => 'required|exists:schools,id',
            'transfer_date' => 'required|date|after:today',
            'reason' => 'required|string|max:500',
            'documents_required' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update student status and school
            $student->update([
                'school_id' => $request->new_school_id,
                'enrollment_status' => 'transferred'
            ]);

            // Create enrollment history
            StudentEnrollmentHistory::create([
                'student_id' => $student->id,
                'status' => 'transferred',
                'changed_by' => Auth::id(),
                'changed_at' => now(),
                'reason' => $request->reason,
                'tenant_id' => $student->tenant_id
            ]);

            // Start transfer workflow
            $workflow = $this->workflowService->startWorkflow($student, 'student_transfer', [
                'steps' => [
                    ['step_number' => 1, 'step_name' => 'document_verification', 'step_type' => 'verification', 'required_role' => 'admin', 'status' => 'pending'],
                    ['step_number' => 2, 'step_name' => 'new_school_approval', 'step_type' => 'approval', 'required_role' => 'admin', 'status' => 'pending'],
                    ['step_number' => 3, 'step_name' => 'records_transfer', 'step_type' => 'transfer', 'required_role' => 'admin', 'status' => 'pending'],
                    ['step_number' => 4, 'step_name' => 'final_confirmation', 'step_type' => 'confirmation', 'required_role' => 'admin', 'status' => 'pending']
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student transfer initiated successfully',
                'data' => [
                    'workflow_id' => $workflow->id,
                    'transfer_date' => $request->transfer_date
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk promote students to next grade level
     */
    public function bulkPromote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'new_grade_level' => 'required|string|max:20',
            'current_academic_year_id' => 'required|exists:academic_years,id',
            'promotion_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $request->student_ids)->get();
            $promotedCount = 0;

            foreach ($students as $student) {
                // Update grade level and academic year
                $student->update([
                    'current_grade_level' => $request->new_grade_level,
                    'current_academic_year_id' => $request->current_academic_year_id
                ]);

                // Create enrollment history
                StudentEnrollmentHistory::create([
                    'student_id' => $student->id,
                    'status' => 'promoted',
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                    'reason' => "Promoted to {$request->new_grade_level}",
                    'tenant_id' => $student->tenant_id
                ]);

                $promotedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully promoted {$promotedCount} students",
                'data' => [
                    'promoted_count' => $promotedCount,
                    'new_grade_level' => $request->new_grade_level,
                    'current_academic_year_id' => $request->current_academic_year_id
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment statistics
     */
    public function enrollmentStats(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_students' => Student::count(),
                'by_enrollment_status' => Student::selectRaw('enrollment_status, COUNT(*) as count')
                    ->groupBy('enrollment_status')
                    ->get(),
                'by_grade_level' => Student::selectRaw('current_grade_level, COUNT(*) as count')
                    ->groupBy('current_grade_level')
                    ->get(),
                'by_school' => Student::selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,display_name')
                    ->groupBy('school_id')
                    ->get(),
                'recent_enrollments' => Student::where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get enrollment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique student number
     */
    private function generateStudentNumber(): string
    {
        $prefix = 'STU';
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}{$year}{$random}";
    }

    /**
     * Get academic progress for student
     */
    private function getAcademicProgress(Student $student): array
    {
        // Implementation for academic progress
        return [
            'current_grade_level' => $student->current_grade_level,
            'academic_year' => $student->currentAcademicYear?->name,
            'enrollment_status' => $student->enrollment_status,
            'current_gpa' => $student->current_gpa,
            'attendance_rate' => $student->attendance_rate,
            'behavioral_points' => $student->behavioral_points
        ];
    }

    /**
     * Get attendance summary for student
     */
    private function getAttendanceSummary(Student $student): array
    {
        // Implementation for attendance summary
        return [
            'total_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'attendance_rate' => $student->attendance_rate ?? 0
        ];
    }

    /**
     * Get documents status for student
     */
    private function getDocumentsStatus(Student $student): array
    {
        $documents = $student->documents()->select('document_type', 'status', 'expiry_date')->get();

        return [
            'total_documents' => $documents->count(),
            'valid_documents' => $documents->where('status', 'valid')->count(),
            'expired_documents' => $documents->where('expiry_date', '<', now())->count(),
            'missing_documents' => $this->getMissingDocuments($student),
            'learning_profile' => $student->learning_profile_json,
            'accommodation_needs' => $student->accommodation_needs_json,
            'language_profile' => $student->language_profile_json,
            'emergency_contacts' => $student->emergency_contacts_json
        ];
    }

    /**
     * Get missing documents for student
     */
    private function getMissingDocuments(Student $student): array
    {
        $requiredDocuments = ['birth_certificate', 'immunization_record', 'parent_consent'];
        $existingDocuments = $student->documents()->pluck('document_type')->toArray();

        return array_diff($requiredDocuments, $existingDocuments);
    }

    /**
     * Get default permissions for students
     */
    private function getDefaultStudentPermissions(): array
    {
        return [
            'view_grades',
            'view_assignments',
            'view_schedule',
            'view_announcements',
            'view_attendance',
            'submit_assignments',
            'view_profile',
            'update_profile'
        ];
    }
}

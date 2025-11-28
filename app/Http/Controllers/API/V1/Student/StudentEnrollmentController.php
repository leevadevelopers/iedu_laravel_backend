<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentEnrollmentHistoryResource;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\School\School;
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

class StudentEnrollmentController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
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
     * Verify that a school_id belongs to the user's tenant
     */
    protected function verifySchoolAccess(int $schoolId): bool
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return false;
        }

        // Check if school belongs to user's tenant
        $school = School::where('id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->exists();

        return $school;
    }

    /**
     * Ensure form template exists, create if it doesn't
     */
    protected function ensureFormTemplateExists(string $formType, int $tenantId, int $userId): FormTemplate
    {
        // Check if template already exists
        $template = FormTemplate::where('category', $formType)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $template;
        }

        // Create default template for student enrollment
        return FormTemplate::create([
            'tenant_id' => $tenantId,
            'name' => 'Student Enrollment Form',
            'description' => 'Default form template for student enrollment. You can customize this template later.',
            'category' => $formType,
            'version' => '1.0',
            'estimated_completion_time' => '15 minutes',
            'is_multi_step' => false,
            'auto_save' => true,
            'compliance_level' => 'standard',
            'is_active' => true,
            'is_default' => true,
            'form_configuration' => [
                'type' => 'student_enrollment',
                'auto_approve' => false,
                'require_comments' => false,
                'allow_draft' => true
            ],
            'steps' => [
                [
                    'step_id' => 'step_1',
                    'step_title' => 'Enrollment Information',
                    'step_number' => 1,
                    'sections' => [
                        [
                            'section_id' => 'section_enrollment_details',
                            'section_title' => 'Enrollment Details',
                            'fields' => [
                                [
                                    'field_id' => 'grade_level_at_enrollment',
                                    'field_type' => 'text',
                                    'label' => 'Grade Level at Enrollment',
                                    'placeholder' => 'e.g., 5, 6, 7',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:20']
                                ],
                                [
                                    'field_id' => 'enrollment_date',
                                    'field_type' => 'date',
                                    'label' => 'Enrollment Date',
                                    'required' => true,
                                    'validation' => ['required', 'date']
                                ],
                                [
                                    'field_id' => 'enrollment_type',
                                    'field_type' => 'select',
                                    'label' => 'Enrollment Type',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'new', 'label' => 'New Enrollment'],
                                        ['value' => 'transfer_in', 'label' => 'Transfer In'],
                                        ['value' => 're_enrollment', 'label' => 'Re-enrollment']
                                    ],
                                    'validation' => ['required', 'in:new,transfer_in,re_enrollment']
                                ]
                            ]
                        ],
                        [
                            'section_id' => 'section_previous_school',
                            'section_title' => 'Previous School Information',
                            'fields' => [
                                [
                                    'field_id' => 'previous_school',
                                    'field_type' => 'text',
                                    'label' => 'Previous School',
                                    'placeholder' => 'Enter previous school name',
                                    'required' => false,
                                    'validation' => ['nullable', 'string', 'max:255']
                                ],
                                [
                                    'field_id' => 'transfer_documents_json',
                                    'field_type' => 'file',
                                    'label' => 'Transfer Documents',
                                    'required' => false,
                                    'validation' => ['nullable', 'array']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'validation_rules' => [
                'grade_level_at_enrollment' => 'required|string|max:20',
                'enrollment_date' => 'required|date',
                'enrollment_type' => 'required|in:new,transfer_in,re_enrollment',
                'previous_school' => 'nullable|string|max:255',
                'transfer_documents_json' => 'nullable|array'
            ],
            'workflow_configuration' => [],
            'metadata' => [
                'auto_created' => true,
                'created_for' => 'student_enrollment',
                'can_be_customized' => true
            ],
            'created_by' => $userId
        ]);
    }

    /**
     * Display a listing of enrollment records with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = StudentEnrollmentHistory::with([
                'student:id,first_name,last_name,student_number,current_grade_level',
                'school:id,official_name,display_name,short_name',
                'academicYear:id,name'
            ]);

            // Apply school_id filter
            // Always use user's school_id or verify requested school_id belongs to user's tenant
            if ($request->has('school_id')) {
                $requestedSchoolId = $request->school_id;
                if ($this->verifySchoolAccess($requestedSchoolId)) {
                    $query->where('school_id', $requestedSchoolId);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this school'
                    ], 403);
                }
            } else {
                // Auto-filter by user's school_id
                $userSchoolId = $this->getCurrentSchoolId();
                if ($userSchoolId) {
                    $query->where('school_id', $userSchoolId);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not associated with any school'
                    ], 403);
                }
            }

            // tenant_id is automatically filtered by Tenantable trait

            // Apply filters
            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('enrollment_type')) {
                $query->where('enrollment_type', $request->enrollment_type);
            }

            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('student', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_number', 'like', "%{$search}%");
                });
            }

            $enrollments = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => StudentEnrollmentHistoryResource::collection($enrollments->items())->resolve(),
                'pagination' => [
                    'current_page' => $enrollments->currentPage(),
                    'per_page' => $enrollments->perPage(),
                    'total' => $enrollments->total(),
                    'last_page' => $enrollments->lastPage(),
                    'from' => $enrollments->firstItem(),
                    'to' => $enrollments->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollment records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new enrollment record with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        // Get school_id from user if not provided
        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Verify school access if school_id is provided in request
        if ($request->has('school_id')) {
            if (!$this->verifySchoolAccess($request->school_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }
            $schoolId = $request->school_id;
        }

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level_at_enrollment' => 'required|string|max:20',
            'enrollment_date' => 'required|date',
            'enrollment_type' => 'required|in:new,transfer_in,re_enrollment',
            'previous_school' => 'nullable|string|max:255',
            'next_school' => 'nullable|string|max:255',
            'withdrawal_reason' => 'nullable|string|max:500',
            'transfer_documents_json' => 'nullable|array',
            'final_gpa' => 'nullable|numeric|min:0|max:4.0',
            'credits_earned' => 'nullable|numeric|min:0',
            'academic_records_json' => 'nullable|array',
            'form_data' => 'nullable|array', // For Form Engine integration
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

            // Ensure form template exists before processing
            if ($request->has('form_data')) {
                $this->ensureFormTemplateExists('student_enrollment', $tenantId, $user->id);
            }

            // Create enrollment record using correct model attributes
            $enrollmentData = $request->only([
                'student_id',
                'academic_year_id',
                'grade_level_at_enrollment',
                'enrollment_date',
                'enrollment_type',
                'previous_school',
                'next_school',
                'withdrawal_reason',
                'transfer_documents_json',
                'final_gpa',
                'credits_earned',
                'academic_records_json'
            ]);

            // Add tenant_id and school_id
            $enrollmentData['tenant_id'] = $tenantId;
            $enrollmentData['school_id'] = $schoolId;

            $enrollment = StudentEnrollmentHistory::create($enrollmentData);

            // Update student's current enrollment info (if Student model has these fields)
            $student = Student::find($request->student_id);
            $studentUpdateData = [
                'school_id' => $schoolId,
                'current_academic_year_id' => $request->academic_year_id,
                'current_grade_level' => $request->grade_level_at_enrollment,
                'admission_date' => $request->enrollment_date
            ];

            // Only update fields that exist in Student model
            $student->update(array_filter($studentUpdateData, function($value, $key) use ($student) {
                return $student->isFillable($key) && $value !== null;
            }, ARRAY_FILTER_USE_BOTH));

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('student_enrollment', $request->form_data, $tenantId);
                $this->formEngineService->createFormInstance('student_enrollment', $processedData, 'StudentEnrollmentHistory', $enrollment->id, $tenantId);
            }

            // Start enrollment workflow
            $workflow = $this->workflowService->startWorkflow($enrollment, 'enrollment_processing', [
                'steps' => [
                    [
                        'step_number' => 1,
                        'step_name' => 'Document Verification',
                        'step_type' => 'verification',
                        'required_role' => 'registrar',
                        'instructions' => 'Verify all required documents are submitted and valid'
                    ],
                    [
                        'step_number' => 2,
                        'step_name' => 'Academic Assessment',
                        'step_type' => 'assessment',
                        'required_role' => 'academic_coordinator',
                        'instructions' => 'Review academic records and determine appropriate grade placement'
                    ],
                    [
                        'step_number' => 3,
                        'step_name' => 'Parent Consent',
                        'step_type' => 'approval',
                        'required_role' => 'parent',
                        'instructions' => 'Obtain parental consent for enrollment'
                    ],
                    [
                        'step_number' => 4,
                        'step_name' => 'Final Approval',
                        'step_type' => 'approval',
                        'required_role' => 'principal',
                        'instructions' => 'Final review and approval of enrollment'
                    ]
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record created successfully',
                'data' => [
                    'enrollment' => $enrollment->load(['student', 'school', 'academicYear']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified enrollment record
     */
    public function show($id): JsonResponse
    {
        try {
            $enrollment = StudentEnrollmentHistory::with([
                'student:id,first_name,last_name,student_number,current_grade_level,email,phone,date_of_birth',
                'school:id,official_name,display_name,short_name,address_json,school_type,email,phone',
                'academicYear:id,name,start_date,end_date'
            ])->find($id);

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enrollment record not found',
                    'error' => 'No enrollment record found with ID: ' . $id
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record retrieved successfully',
                'data' => new StudentEnrollmentHistoryResource($enrollment)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified enrollment record
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grade_level_at_enrollment' => 'sometimes|required|string|max:20',
            'grade_level_at_withdrawal' => 'nullable|string|max:20',
            'enrollment_type' => 'sometimes|required|in:new,transfer_in,re_enrollment',
            'withdrawal_type' => 'nullable|in:graduation,transfer_out,dropout,other',
            'withdrawal_date' => 'nullable|date',
            'withdrawal_reason' => 'nullable|string|max:500',
            'previous_school' => 'nullable|string|max:255',
            'next_school' => 'nullable|string|max:255',
            'transfer_documents_json' => 'nullable|array',
            'final_gpa' => 'nullable|numeric|min:0|max:4.0',
            'credits_earned' => 'nullable|numeric|min:0',
            'academic_records_json' => 'nullable|array',
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

            $enrollment = StudentEnrollmentHistory::find($id);

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enrollment record not found',
                    'error' => 'No enrollment record found with ID: ' . $id
                ], 404);
            }

            // Only update fields that are fillable in the model
            $updateData = $request->only([
                'grade_level_at_enrollment',
                'grade_level_at_withdrawal',
                'enrollment_type',
                'withdrawal_type',
                'withdrawal_date',
                'withdrawal_reason',
                'previous_school',
                'next_school',
                'transfer_documents_json',
                'final_gpa',
                'credits_earned',
                'academic_records_json'
            ]);

            $enrollment->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record updated successfully',
                'data' => $enrollment->fresh()->load(['student', 'school', 'academicYear'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified enrollment record
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $enrollment = StudentEnrollmentHistory::find($id);

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enrollment record not found',
                    'error' => 'No enrollment record found with ID: ' . $id
                ], 404);
            }

            // Soft delete enrollment record
            $enrollment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollment record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete enrollment record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment records by student
     */
    public function getByStudent(int $studentId): JsonResponse
    {
        try {
            $enrollments = StudentEnrollmentHistory::where('student_id', $studentId)
                ->with(['school:id,official_name,display_name,short_name', 'academicYear:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $enrollments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student enrollment records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current enrollment status for a student
     */
    public function getCurrentEnrollment(int $studentId): JsonResponse
    {
        try {
            $currentEnrollment = StudentEnrollmentHistory::where('student_id', $studentId)
                ->with(['school:id,official_name,display_name,short_name', 'academicYear:id,name'])
                ->latest('created_at')
                ->first();

            if (!$currentEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No enrollment record found for this student'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $currentEnrollment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current enrollment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk enroll students
     */
    public function bulkEnroll(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        // Get school_id from user if not provided
        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Verify school access if school_id is provided in request
        if ($request->has('school_id')) {
            if (!$this->verifySchoolAccess($request->school_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }
            $schoolId = $request->school_id;
        }

        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'school_id' => 'sometimes|exists:schools,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'grade_level_at_enrollment' => 'required|string|max:20',
            'enrollment_date' => 'required|date',
            'enrollment_type' => 'required|in:new,transfer_in,re_enrollment',
            'previous_school' => 'nullable|string|max:255',
            'withdrawal_reason' => 'nullable|string|max:500',
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
            $enrolledCount = 0;

            foreach ($students as $student) {
                // Create enrollment record using correct model attributes
                StudentEnrollmentHistory::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'academic_year_id' => $request->academic_year_id,
                    'grade_level_at_enrollment' => $request->grade_level_at_enrollment,
                    'enrollment_date' => $request->enrollment_date,
                    'enrollment_type' => $request->enrollment_type,
                    'previous_school' => $request->previous_school,
                    'withdrawal_reason' => $request->withdrawal_reason,
                ]);

                // Update student's current enrollment info (if Student model has these fields)
                $studentUpdateData = [
                    'school_id' => $schoolId,
                    'current_academic_year_id' => $request->academic_year_id,
                    'current_grade_level' => $request->grade_level_at_enrollment,
                    'admission_date' => $request->enrollment_date
                ];

                // Only update fields that exist in Student model
                $student->update(array_filter($studentUpdateData, function($value, $key) use ($student) {
                    return $student->isFillable($key) && $value !== null;
                }, ARRAY_FILTER_USE_BOTH));

                $enrolledCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully enrolled {$enrolledCount} students",
                'data' => [
                    'enrolled_count' => $enrolledCount,
                    'school_id' => $schoolId,
                    'academic_year_id' => $request->academic_year_id,
                    'grade_level_at_enrollment' => $request->grade_level_at_enrollment
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk enroll students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer multiple students
     */
    public function bulkTransfer(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'new_school_id' => 'required|exists:schools,id',
            'transfer_date' => 'required|date|after:today',
            'withdrawal_reason' => 'required|string|max:500',
            'next_school' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify school access
        if (!$this->verifySchoolAccess($request->new_school_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this school'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $students = Student::whereIn('id', $request->student_ids)->get();
            $transferredCount = 0;

            foreach ($students as $student) {
                // Create transfer enrollment record using correct model attributes
                StudentEnrollmentHistory::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'school_id' => $request->new_school_id,
                    'academic_year_id' => $student->current_academic_year_id ?? 1, // Default if not set
                    'grade_level_at_enrollment' => $student->current_grade_level ?? 'Unknown',
                    'enrollment_date' => $request->transfer_date,
                    'enrollment_type' => 'transfer_in',
                    'withdrawal_reason' => $request->withdrawal_reason,
                    'next_school' => $request->next_school,
                ]);

                // Update student's current school (if Student model has these fields)
                $studentUpdateData = [
                    'school_id' => $request->new_school_id,
                ];

                // Only update fields that exist in Student model
                $student->update(array_filter($studentUpdateData, function($value, $key) use ($student) {
                    return $student->isFillable($key) && $value !== null;
                }, ARRAY_FILTER_USE_BOTH));

                $transferredCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully transferred {$transferredCount} students",
                'data' => [
                    'transferred_count' => $transferredCount,
                    'new_school_id' => $request->new_school_id,
                    'transfer_date' => $request->transfer_date
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrollment trends over time
     */
    public function getEnrollmentTrends(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'monthly'); // daily, weekly, monthly, yearly
            $dateFrom = $request->get('date_from', now()->subYear());
            $dateTo = $request->get('date_to', now());

            $query = StudentEnrollmentHistory::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date');

            if ($period === 'weekly') {
                $query = StudentEnrollmentHistory::selectRaw('YEARWEEK(created_at) as week, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->groupBy('week')
                    ->orderBy('week');
            } elseif ($period === 'monthly') {
                $query = StudentEnrollmentHistory::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->groupBy('month')
                    ->orderBy('month');
            } elseif ($period === 'yearly') {
                $query = StudentEnrollmentHistory::selectRaw('YEAR(created_at) as year, COUNT(*) as count')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->groupBy('year')
                    ->orderBy('year');
            }

            $trends = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'trends' => $trends
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get enrollment trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

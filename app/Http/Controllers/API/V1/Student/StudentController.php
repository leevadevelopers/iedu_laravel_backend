<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\SchoolUser;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\User;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

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

        // Create default template for student registration
        return FormTemplate::create([
            'tenant_id' => $tenantId,
            'name' => 'Student Registration Form',
            'description' => 'Default form template for student registration. You can customize this template later.',
            'category' => $formType,
            'version' => '1.0',
            'estimated_completion_time' => '20 minutes',
            'is_multi_step' => false,
            'auto_save' => true,
            'compliance_level' => 'standard',
            'is_active' => true,
            'is_default' => true,
            'form_configuration' => [
                'type' => 'student_registration',
                'auto_approve' => false,
                'require_comments' => false,
                'allow_draft' => true
            ],
            'steps' => [
                [
                    'step_id' => 'step_1',
                    'step_title' => 'Student Information',
                    'step_number' => 1,
                    'sections' => [
                        [
                            'section_id' => 'section_personal_info',
                            'section_title' => 'Personal Information',
                            'fields' => [
                                [
                                    'field_id' => 'first_name',
                                    'field_type' => 'text',
                                    'label' => 'First Name',
                                    'placeholder' => 'Enter first name',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:100']
                                ],
                                [
                                    'field_id' => 'middle_name',
                                    'field_type' => 'text',
                                    'label' => 'Middle Name',
                                    'placeholder' => 'Enter middle name',
                                    'required' => false,
                                    'validation' => ['nullable', 'string', 'max:100']
                                ],
                                [
                                    'field_id' => 'last_name',
                                    'field_type' => 'text',
                                    'label' => 'Last Name',
                                    'placeholder' => 'Enter last name',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:100']
                                ],
                                [
                                    'field_id' => 'date_of_birth',
                                    'field_type' => 'date',
                                    'label' => 'Date of Birth',
                                    'required' => true,
                                    'validation' => ['required', 'date', 'before:today']
                                ],
                                [
                                    'field_id' => 'gender',
                                    'field_type' => 'select',
                                    'label' => 'Gender',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'male', 'label' => 'Male'],
                                        ['value' => 'female', 'label' => 'Female'],
                                        ['value' => 'other', 'label' => 'Other']
                                    ],
                                    'validation' => ['required', 'in:male,female,other']
                                ]
                            ]
                        ],
                        [
                            'section_id' => 'section_contact_info',
                            'section_title' => 'Contact Information',
                            'fields' => [
                                [
                                    'field_id' => 'email',
                                    'field_type' => 'email',
                                    'label' => 'Email',
                                    'placeholder' => 'Enter email address',
                                    'required' => false,
                                    'validation' => ['nullable', 'email', 'max:255']
                                ],
                                [
                                    'field_id' => 'phone',
                                    'field_type' => 'tel',
                                    'label' => 'Phone',
                                    'placeholder' => 'Enter phone number',
                                    'required' => false,
                                    'validation' => ['nullable', 'string', 'max:20']
                                ]
                            ]
                        ],
                        [
                            'section_id' => 'section_enrollment_info',
                            'section_title' => 'Enrollment Information',
                            'fields' => [
                                [
                                    'field_id' => 'current_grade_level',
                                    'field_type' => 'text',
                                    'label' => 'Grade Level',
                                    'placeholder' => 'e.g., 5, 6, 7',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:20']
                                ],
                                [
                                    'field_id' => 'admission_date',
                                    'field_type' => 'date',
                                    'label' => 'Admission Date',
                                    'required' => true,
                                    'validation' => ['required', 'date']
                                ],
                                [
                                    'field_id' => 'enrollment_status',
                                    'field_type' => 'select',
                                    'label' => 'Enrollment Status',
                                    'required' => true,
                                    'options' => [
                                        ['value' => 'enrolled', 'label' => 'Enrolled'],
                                        ['value' => 'transferred', 'label' => 'Transferred'],
                                        ['value' => 'graduated', 'label' => 'Graduated'],
                                        ['value' => 'withdrawn', 'label' => 'Withdrawn'],
                                        ['value' => 'suspended', 'label' => 'Suspended']
                                    ],
                                    'validation' => ['required', 'in:enrolled,transferred,graduated,withdrawn,suspended']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'validation_rules' => [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'middle_name' => 'nullable|string|max:100',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:male,female,other',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'current_grade_level' => 'required|string|max:20',
                'admission_date' => 'required|date',
                'enrollment_status' => 'required|in:enrolled,transferred,graduated,withdrawn,suspended'
            ],
            'workflow_configuration' => [],
            'metadata' => [
                'auto_created' => true,
                'created_for' => 'student_registration',
                'can_be_customized' => true
            ],
            'created_by' => $userId
        ]);
    }

    /**
     * Display a listing of students with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Student::with([
                'enrollmentHistory',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear'
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

            // Apply filters
            if ($request->has('enrollment_status')) {
                $query->where('enrollment_status', $request->enrollment_status);
            }

            if ($request->has('current_grade_level')) {
                $query->where('current_grade_level', $request->current_grade_level);
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

            // tenant_id is automatically filtered by Tenantable trait

            $students = $query->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => StudentResource::collection($students->items())->resolve(),
                'pagination' => [
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'last_page' => $students->lastPage(),
                    'from' => $students->firstItem(),
                    'to' => $students->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created student with Form Engine processing
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
            'user_id' => 'nullable|exists:users,id',
            'user_data' => 'nullable|array', // For creating new user when user_id is provided
            'user_data.name' => 'nullable|string|max:255',
            'user_data.email' => 'nullable|email|max:255|unique:users,identifier',
            'user_data.phone' => 'nullable|string|max:20|unique:users,identifier',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'birth_place' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female,other',
            'nationality' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255|unique:students,email',
            'phone' => 'nullable|string|max:20',
            'address_json' => 'nullable|array',
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userId = null;

            // If user_id is provided, use existing user_id
            if ($request->has('user_id') && $request->user_id) {
                $userId = $request->user_id;
            } else {
                // If user_id is not provided, create new user automatically
                // Use user_data if provided, otherwise use student data
                $userData = $request->user_data ?? [];
                $identifier = $userData['email'] ?? $userData['phone'] ?? $request->email ?? $request->phone;
                $userType = isset($userData['email']) || isset($request->email) ? 'email' : 'phone';
                $userName = $userData['name'] ?? "{$request->first_name} {$request->last_name}";

                if (!$identifier) {
                    return response()->json([
                        'success' => false,
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
                        'password' => bcrypt('@EstudanteIedu'),
                        'must_change' => true,
                        'tenant_id' => $tenantId,
                        'is_active' => true,
                    ]);

                    $userId = $newUser->id;

                    // Create TenantUser association
                    $newUser->tenants()->attach($tenantId, [
                        'status' => 'active',
                        'joined_at' => now(),
                        'role_id' => Role::where('name', 'student')->first()->id,
                        'current_tenant' => true,
                    ]);
                }
            }

            // Ensure user_id is set
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create or retrieve user. Email or phone is required.'
                ], 422);
            }

            // Create student
            $studentData = $request->except(['form_data', 'family_relationships', 'user_data']);
            $studentData['tenant_id'] = $tenantId;
            $studentData['school_id'] = $schoolId;
            $studentData['user_id'] = $userId;
            $studentData['student_number'] = $this->generateStudentNumber($schoolId);

            // Set default values
            $studentData['behavioral_points'] = $studentData['behavioral_points'] ?? 0;

            $student = Student::create($studentData);

            // Create or update SchoolUser association (idempotent to avoid duplicates)
            if ($student->user_id && $student->school_id) {
                SchoolUser::updateOrCreate(
                    [
                        'school_id' => $student->school_id,
                        'user_id'   => $student->user_id,
                    ],
                    [
                        'role'        => 'student',
                        'status'      => 'active',
                        'start_date'  => now(),
                        'permissions' => $this->getDefaultStudentPermissions(),
                    ]
                );
            }

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                // Ensure form template exists, create if it doesn't
                $this->ensureFormTemplateExists('student_registration', $tenantId, $user->id);

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

            // Send welcome emails
            try {
                $emailService = app(\App\Services\Email\EmailService::class);

                // Send email to student if they have email
                $emailService->sendStudentWelcomeEmail($student);

                // Send emails to all parents/guardians
                $emailService->sendParentNotificationEmails($student);
            } catch (\Exception $e) {
                Log::warning('Failed to send welcome emails for student', [
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'student' => $student->load(['school', 'currentAcademicYear']),
                    'workflow_id' => $workflow->id,
                    'student_number' => $student->student_number,
                    'user_id' => $userId
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified student
     */
    public function show(Student $student): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $student->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this student'
                ], 403);
            }

            $student->load([
                'enrollmentHistory',
                'documents',
                'familyRelationships',
                'school',
                'currentAcademicYear'
            ]);

            return response()->json([
                'success' => true,
                'data' => $student
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified student
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        // Verify access: must belong to user's school
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId || $student->school_id != $userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this student'
            ], 403);
        }

        // Verify school access if school_id is being changed
        if ($request->has('school_id') && $request->school_id != $student->school_id) {
            if (!$this->verifySchoolAccess($request->school_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
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

            // Get only fillable fields from request, excluding tenant_id (should not be changed)
            $allowedFields = [
                'user_id',
                'school_id',
                'first_name',
                'middle_name',
                'last_name',
                'date_of_birth',
                'birth_place',
                'gender',
                'nationality',
                'email',
                'phone',
                'address_json',
                'admission_date',
                'current_grade_level',
                'current_academic_year_id',
                'enrollment_status',
                'expected_graduation_date',
                'learning_profile_json',
                'accommodation_needs_json',
                'language_profile_json',
                'medical_information_json',
                'emergency_contacts_json',
                'special_circumstances_json',
                'current_gpa',
                'attendance_rate',
                'behavioral_points',
            ];

            // Only include fields that are present in the request
            $updateData = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            // Store original enrollment status before update
            $originalEnrollmentStatus = $student->enrollment_status;

            // Update student with filtered data (only if there's data to update)
            if (!empty($updateData)) {
                $student->update($updateData);
            }

            // If enrollment status changed, create enrollment history
            if ($request->has('enrollment_status') && $request->enrollment_status !== $originalEnrollmentStatus) {
                // Get current academic year or use the one from request
                $academicYearId = $request->current_academic_year_id ?? $student->current_academic_year_id;

                if ($academicYearId) {
                    StudentEnrollmentHistory::create([
                        'school_id' => $student->school_id,
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYearId,
                        'enrollment_date' => $student->admission_date ?? now(),
                        'grade_level_at_enrollment' => $student->current_grade_level,
                        'enrollment_type' => $request->enrollment_status === 'enrolled' ? 're_enrollment' : 'new',
                        'withdrawal_reason' => $request->get('status_change_reason'),
                        'academic_records_json' => [
                            'status_changed_by' => Auth::id(),
                            'status_changed_at' => now()->toDateTimeString(),
                            'previous_status' => $originalEnrollmentStatus,
                            'new_status' => $request->enrollment_status
                        ]
                    ]);
                }
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
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $student->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this student'
                ], 403);
            }

            DB::beginTransaction();

            // Remove SchoolUser association
            if ($student->user_id && $student->school_id) {
                SchoolUser::where('user_id', $student->user_id)
                    ->where('school_id', $student->school_id)
                    ->delete();
            }

            // Remove TenantUser association
            if ($student->user_id && $student->tenant_id) {
                $student->user->tenants()->detach($student->tenant_id);
            }

            // Soft delete related documents
            $student->documents()->delete();

            // Soft delete student
            $student->delete();

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
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $student->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this student'
                ], 403);
            }

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
        // Verify access: must belong to user's school
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId || $student->school_id != $userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this student'
            ], 403);
        }

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

        // Verify access to new school
        if (!$this->verifySchoolAccess($request->new_school_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to the target school'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Update student status and school
            $student->update([
                'school_id' => $request->new_school_id,
                'enrollment_status' => 'transferred'
            ]);

            // Update SchoolUser association to new school
            if ($student->user_id) {
                // Remove old school association
                SchoolUser::where('user_id', $student->user_id)
                    ->where('school_id', $userSchoolId)
                    ->delete();

                // Create new school association
                SchoolUser::create([
                    'school_id' => $request->new_school_id,
                    'user_id' => $student->user_id,
                    'role' => 'student',
                    'status' => 'active',
                    'start_date' => now(),
                    'permissions' => $this->getDefaultStudentPermissions(),
                ]);
            }

            // Create enrollment history for transfer
            $academicYearId = $student->current_academic_year_id ?? $request->current_academic_year_id;

            if ($academicYearId) {
                StudentEnrollmentHistory::create([
                    'school_id' => $request->new_school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYearId,
                    'enrollment_date' => $request->transfer_date,
                    'grade_level_at_enrollment' => $student->current_grade_level,
                    'enrollment_type' => 'transfer_in',
                    'previous_school' => School::find($userSchoolId)?->display_name ?? School::find($userSchoolId)?->official_name,
                    'next_school' => School::find($request->new_school_id)?->display_name ?? School::find($request->new_school_id)?->official_name,
                    'withdrawal_reason' => $request->reason,
                    'academic_records_json' => [
                        'transferred_by' => Auth::id(),
                        'transferred_at' => now()->toDateTimeString(),
                        'transfer_reason' => $request->reason
                    ]
                ]);
            }

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
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

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

            // Filter students by user's school
            $students = Student::whereIn('id', $request->student_ids)
                ->where('school_id', $userSchoolId)
                ->get();

            if ($students->count() !== count($request->student_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some students do not belong to your school'
                ], 403);
            }

            $promotedCount = 0;

            foreach ($students as $student) {
                // Store old grade level before update
                $oldGradeLevel = $student->current_grade_level;

                // Update grade level and academic year
                $student->update([
                    'current_grade_level' => $request->new_grade_level,
                    'current_academic_year_id' => $request->current_academic_year_id
                ]);

                // Create enrollment history for promotion
                StudentEnrollmentHistory::create([
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                    'academic_year_id' => $request->current_academic_year_id,
                    'enrollment_date' => $request->promotion_date,
                    'grade_level_at_enrollment' => $request->new_grade_level,
                    'enrollment_type' => 're_enrollment',
                    'academic_records_json' => [
                        'previous_grade_level' => $oldGradeLevel,
                        'promotion_reason' => "Promoted to {$request->new_grade_level}",
                        'promoted_by' => Auth::id(),
                        'promoted_at' => now()->toDateTimeString()
                    ]
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
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }

            // Filter by user's school
            $query = Student::where('school_id', $userSchoolId);

            $stats = [
                'total_students' => (clone $query)->count(),
                'by_enrollment_status' => (clone $query)->selectRaw('enrollment_status, COUNT(*) as count')
                    ->groupBy('enrollment_status')
                    ->get(),
                'by_grade_level' => (clone $query)->selectRaw('current_grade_level, COUNT(*) as count')
                    ->groupBy('current_grade_level')
                    ->get(),
                'by_school' => (clone $query)->selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,display_name')
                    ->groupBy('school_id')
                    ->get(),
                'recent_enrollments' => (clone $query)->where('created_at', '>=', now()->subDays(30))
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
        $documents = $student->documents()->select('document_type', 'status', 'expiration_date')->get();

        $expiredCount = $documents->filter(function($doc) {
            return $doc->expiration_date && $doc->expiration_date->isPast();
        })->count();

        return [
            'total_documents' => $documents->count(),
            'approved_documents' => $documents->where('status', 'approved')->count(),
            'pending_documents' => $documents->where('status', 'pending')->count(),
            'rejected_documents' => $documents->where('status', 'rejected')->count(),
            'expired_documents' => $expiredCount,
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
        $requiredDocuments = ['birth_certificate', 'vaccination_records', 'identification'];
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

    /**
     * Import students from CSV file
     */
    public function import(\App\Http\Requests\Student\ImportStudentsRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $skipDuplicates = $request->boolean('skip_duplicates', true);
            $updateExisting = $request->boolean('update_existing', false);
            $validateOnly = $request->boolean('validate_only', false);

            $tenantId = $request->input('tenant_id') ?? $this->getCurrentTenantId();
            $schoolId = $request->input('school_id') ?? $this->getCurrentSchoolId();

            if (!$tenantId || !$schoolId) {
                return $this->errorResponse('Tenant ID and School ID are required', 400);
            }

            // Verify school access
            if (!$this->verifySchoolAccess($schoolId)) {
                return $this->errorResponse('Unauthorized access to school', 403);
            }

            $results = [
                'imported' => 0,
                'skipped' => 0,
                'updated' => 0,
                'errors' => [],
                'warnings' => []
            ];

            // Parse CSV file
            $filePath = $file->getRealPath();
            $fileHandle = fopen($filePath, 'r');
            
            if (!$fileHandle) {
                return $this->errorResponse('Failed to read CSV file', 500);
            }

            // Read header row
            $headers = fgetcsv($fileHandle);
            if (!$headers) {
                fclose($fileHandle);
                return $this->errorResponse('CSV file is empty or invalid', 400);
            }

            // Normalize headers (trim, lowercase, replace spaces with underscores)
            $headers = array_map(function($header) {
                return strtolower(trim(str_replace(' ', '_', $header)));
            }, $headers);

            // Required columns
            $requiredColumns = ['first_name', 'last_name', 'date_of_birth', 'gender'];
            $missingColumns = array_diff($requiredColumns, $headers);
            
            if (!empty($missingColumns)) {
                fclose($fileHandle);
                return $this->errorResponse(
                    'Missing required columns: ' . implode(', ', $missingColumns),
                    400
                );
            }

            $rowNumber = 1; // Start from 1 (header is row 0)

            // Process each row
            while (($row = fgetcsv($fileHandle)) !== false) {
                $rowNumber++;
                
                try {
                    // Map row data to associative array
                    $rowData = [];
                    foreach ($headers as $index => $header) {
                        $rowData[$header] = $row[$index] ?? null;
                    }

                    // Skip empty rows
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    // Validate required fields
                    $validationErrors = $this->validateStudentRow($rowData, $rowNumber);
                    if (!empty($validationErrors)) {
                        $results['errors'] = array_merge($results['errors'], $validationErrors);
                        continue;
                    }

                    // If validate_only, skip actual import
                    if ($validateOnly) {
                        continue;
                    }

                    // Check for duplicates
                    $existingStudent = $this->findDuplicateStudent($rowData, $schoolId);

                    if ($existingStudent) {
                        if ($updateExisting) {
                            // Update existing student
                            $this->updateStudentFromRow($existingStudent, $rowData, $tenantId, $schoolId);
                            $results['updated']++;
                        } else if ($skipDuplicates) {
                            $results['skipped']++;
                            $results['warnings'][] = [
                                'row' => $rowNumber,
                                'message' => 'Student already exists (skipped)',
                                'value' => $rowData['email'] ?? $rowData['student_number'] ?? 'N/A'
                            ];
                        } else {
                            $results['errors'][] = [
                                'row' => $rowNumber,
                                'field' => 'email',
                                'message' => 'Student already exists',
                                'value' => $rowData['email'] ?? $rowData['student_number'] ?? 'N/A'
                            ];
                        }
                        continue;
                    }

                    // Create new student
                    $this->createStudentFromRow($rowData, $tenantId, $schoolId);
                    $results['imported']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'row' => $rowNumber,
                        'message' => 'Error processing row: ' . $e->getMessage(),
                        'value' => null
                    ];
                    Log::error('Student import error', [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            fclose($fileHandle);

            return $this->successResponse($results, 'Import completed');

        } catch (\Exception $e) {
            Log::error('Student import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download CSV import template
     */
    public function downloadImportTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_import_template.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // Write BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write header row
            fputcsv($file, [
                'first_name',
                'last_name',
                'middle_name',
                'preferred_name',
                'date_of_birth',
                'gender',
                'email',
                'phone',
                'student_number',
                'current_grade_level',
                'admission_date',
                'enrollment_status',
                'nationality',
                'birth_place'
            ]);

            // Write example row
            fputcsv($file, [
                'John',
                'Doe',
                'Michael',
                'Johnny',
                '2010-05-15',
                'male',
                'john.doe@example.com',
                '+1234567890',
                'STU001',
                '8',
                '2024-09-01',
                'enrolled',
                'US',
                'New York'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Validate a single student row
     */
    private function validateStudentRow(array $rowData, int $rowNumber): array
    {
        $errors = [];

        // Required fields
        if (empty($rowData['first_name'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'first_name',
                'message' => 'First name is required',
                'value' => $rowData['first_name'] ?? null
            ];
        }

        if (empty($rowData['last_name'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'last_name',
                'message' => 'Last name is required',
                'value' => $rowData['last_name'] ?? null
            ];
        }

        if (empty($rowData['date_of_birth'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'date_of_birth',
                'message' => 'Date of birth is required',
                'value' => $rowData['date_of_birth'] ?? null
            ];
        } else {
            try {
                $dob = \Carbon\Carbon::parse($rowData['date_of_birth']);
                if ($dob->isFuture()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'date_of_birth',
                        'message' => 'Date of birth cannot be in the future',
                        'value' => $rowData['date_of_birth']
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'date_of_birth',
                    'message' => 'Invalid date format',
                    'value' => $rowData['date_of_birth']
                ];
            }
        }

        if (empty($rowData['gender'])) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'gender',
                'message' => 'Gender is required',
                'value' => $rowData['gender'] ?? null
            ];
        } else {
            $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
            if (!in_array(strtolower($rowData['gender']), $validGenders)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'gender',
                    'message' => 'Invalid gender. Must be one of: ' . implode(', ', $validGenders),
                    'value' => $rowData['gender']
                ];
            }
        }

        // Optional but validated fields
        if (!empty($rowData['email']) && !filter_var($rowData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = [
                'row' => $rowNumber,
                'field' => 'email',
                'message' => 'Invalid email format',
                'value' => $rowData['email']
            ];
        }

        if (!empty($rowData['enrollment_status'])) {
            $validStatuses = ['enrolled', 'transferred', 'graduated', 'withdrawn', 'suspended'];
            if (!in_array(strtolower($rowData['enrollment_status']), $validStatuses)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'enrollment_status',
                    'message' => 'Invalid enrollment status',
                    'value' => $rowData['enrollment_status']
                ];
            }
        }

        return $errors;
    }

    /**
     * Find duplicate student by email or student_number
     */
    private function findDuplicateStudent(array $rowData, int $schoolId): ?Student
    {
        $query = Student::where('school_id', $schoolId);

        if (!empty($rowData['email'])) {
            $query->where('email', $rowData['email']);
        } elseif (!empty($rowData['student_number'])) {
            $query->where('student_number', $rowData['student_number']);
        } else {
            // Try to match by first_name, last_name, and date_of_birth
            if (!empty($rowData['first_name']) && !empty($rowData['last_name']) && !empty($rowData['date_of_birth'])) {
                try {
                    $dob = \Carbon\Carbon::parse($rowData['date_of_birth'])->format('Y-m-d');
                    $query->where('first_name', $rowData['first_name'])
                          ->where('last_name', $rowData['last_name'])
                          ->whereDate('date_of_birth', $dob);
                } catch (\Exception $e) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $query->first();
    }

    /**
     * Create student from CSV row data
     */
    private function createStudentFromRow(array $rowData, int $tenantId, int $schoolId): Student
    {
        $studentData = [
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'first_name' => $rowData['first_name'],
            'last_name' => $rowData['last_name'],
            'middle_name' => $rowData['middle_name'] ?? null,
            'preferred_name' => $rowData['preferred_name'] ?? null,
            'date_of_birth' => \Carbon\Carbon::parse($rowData['date_of_birth'])->format('Y-m-d'),
            'gender' => strtolower($rowData['gender']),
            'email' => $rowData['email'] ?? null,
            'phone' => $rowData['phone'] ?? null,
            'student_number' => $rowData['student_number'] ?? $this->generateStudentNumber($schoolId),
            'current_grade_level' => $rowData['current_grade_level'] ?? null,
            'admission_date' => !empty($rowData['admission_date']) 
                ? \Carbon\Carbon::parse($rowData['admission_date'])->format('Y-m-d')
                : now()->format('Y-m-d'),
            'enrollment_status' => strtolower($rowData['enrollment_status'] ?? 'enrolled'),
            'nationality' => $rowData['nationality'] ?? null,
            'birth_place' => $rowData['birth_place'] ?? null,
            'behavioral_points' => 0
        ];

        return Student::create($studentData);
    }

    /**
     * Update existing student from CSV row data
     */
    private function updateStudentFromRow(Student $student, array $rowData, int $tenantId, int $schoolId): void
    {
        $updateData = [];

        if (!empty($rowData['first_name'])) {
            $updateData['first_name'] = $rowData['first_name'];
        }
        if (!empty($rowData['last_name'])) {
            $updateData['last_name'] = $rowData['last_name'];
        }
        if (!empty($rowData['middle_name'])) {
            $updateData['middle_name'] = $rowData['middle_name'];
        }
        if (!empty($rowData['preferred_name'])) {
            $updateData['preferred_name'] = $rowData['preferred_name'];
        }
        if (!empty($rowData['date_of_birth'])) {
            $updateData['date_of_birth'] = \Carbon\Carbon::parse($rowData['date_of_birth'])->format('Y-m-d');
        }
        if (!empty($rowData['gender'])) {
            $updateData['gender'] = strtolower($rowData['gender']);
        }
        if (!empty($rowData['email'])) {
            $updateData['email'] = $rowData['email'];
        }
        if (!empty($rowData['phone'])) {
            $updateData['phone'] = $rowData['phone'];
        }
        if (!empty($rowData['current_grade_level'])) {
            $updateData['current_grade_level'] = $rowData['current_grade_level'];
        }
        if (!empty($rowData['enrollment_status'])) {
            $updateData['enrollment_status'] = strtolower($rowData['enrollment_status']);
        }
        if (!empty($rowData['nationality'])) {
            $updateData['nationality'] = $rowData['nationality'];
        }
        if (!empty($rowData['birth_place'])) {
            $updateData['birth_place'] = $rowData['birth_place'];
        }

        $student->update($updateData);
    }

    /**
     * Generate unique student number
     */
    private function generateStudentNumber(int $schoolId): string
    {
        $prefix = 'STU';
        $year = now()->year;
        
        // Get the last student number for this school
        $lastStudent = Student::where('school_id', $schoolId)
            ->where('student_number', 'like', $prefix . $year . '%')
            ->orderBy('student_number', 'desc')
            ->first();

        if ($lastStudent && preg_match('/' . $prefix . $year . '(\d+)/', $lastStudent->student_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Helper method for success response
     */
    private function successResponse($data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message
        ], $code);
    }

    /**
     * Create a draft student (with minimal validation)
     */
    public function createDraft(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Minimal validation for draft - only require first_name and last_name
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'current_grade_level' => 'nullable|string|max:20',
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

            // Generate student number
            $studentNumber = $this->generateStudentNumber($schoolId);

            // Create draft student with status='draft'
            $studentData = $request->only([
                'first_name', 'middle_name', 'last_name', 'date_of_birth',
                'email', 'phone', 'current_grade_level'
            ]);
            $studentData['tenant_id'] = $tenantId;
            $studentData['school_id'] = $schoolId;
            $studentData['student_number'] = $studentNumber;
            $studentData['status'] = 'draft';
            $studentData['enrollment_status'] = 'enrolled'; // Default
            $studentData['admission_date'] = $request->admission_date ?? now();
            $studentData['behavioral_points'] = 0;

            $student = Student::create($studentData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Draft student created successfully',
                'data' => $student
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create draft student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a draft student (change status from draft to active)
     */
    public function publish(Student $student): JsonResponse
    {
        try {
            // Verify access
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $student->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this student'
                ], 403);
            }

            // Check if student is a draft
            if ($student->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is not a draft. Only draft students can be published.'
                ], 422);
            }

            // Validate required fields before publishing
            $requiredFields = ['first_name', 'last_name', 'date_of_birth', 'current_grade_level', 'admission_date'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($student->$field)) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot publish student. Missing required fields: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields
                ], 422);
            }

            // Update status to active
            $student->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => 'Student published successfully',
                'data' => $student->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish student',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate student enrollment in a class
     */
    public function validateEnrollment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $student = Student::findOrFail($request->student_id);
            $class = \App\Models\V1\Academic\AcademicClass::with(['academicYear', 'subject'])->findOrFail($request->class_id);

            $errors = [];
            $warnings = [];
            $valid = true;

            // Verify access
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $student->school_id != $userSchoolId || $class->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to these resources'
                ], 403);
            }

            // Check 1: Academic year match
            $requestAcademicYearId = $request->academic_year_id ?? $student->current_academic_year_id;
            if ($class->academic_year_id != $requestAcademicYearId) {
                $errors[] = 'Class academic year does not match student\'s current academic year';
                $valid = false;
            }

            // Check 2: Grade level match (warning if different)
            if ($class->grade_level != $student->current_grade_level) {
                $warnings[] = "Class grade level ({$class->grade_level}) does not match student's current grade level ({$student->current_grade_level})";
            }

            // Check 3: Class capacity
            if ($class->current_enrollment >= $class->max_students) {
                $errors[] = 'Class has reached maximum capacity';
                $valid = false;
            }

            // Check 4: Duplicate enrollment
            $existingEnrollment = DB::table('student_class_enrollments')
                ->where('student_id', $student->id)
                ->where('class_id', $class->id)
                ->where('status', 'active')
                ->exists();

            if ($existingEnrollment) {
                $errors[] = 'Student is already enrolled in this class';
                $valid = false;
            }

            return response()->json([
                'success' => true,
                'valid' => $valid,
                'errors' => $errors,
                'warnings' => $warnings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate enrollment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method for error response
     */
    private function errorResponse(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }
}

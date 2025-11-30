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
            $studentData['student_number'] = $this->generateStudentNumber();

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
}

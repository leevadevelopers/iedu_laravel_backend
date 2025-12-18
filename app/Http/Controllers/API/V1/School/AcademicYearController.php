<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\V1\SIS\School\School;
use Carbon\Carbon;
use App\Models\V1\SIS\Student\Student;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AcademicYearController extends Controller
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

        // Create default template
        return FormTemplate::create([
            'tenant_id' => $tenantId,
            'name' => 'Academic Year Setup Form',
            'description' => 'Default form template for academic year setup. You can customize this template later.',
            'category' => $formType,
            'version' => '1.0',
            'estimated_completion_time' => '15 minutes',
            'is_multi_step' => false,
            'auto_save' => true,
            'compliance_level' => 'standard',
            'is_active' => true,
            'is_default' => true,
            'form_configuration' => [
                'type' => 'academic_year_setup',
                'auto_approve' => false,
                'require_comments' => false,
                'allow_draft' => true
            ],
            'steps' => [
                [
                    'step_id' => 'step_1',
                    'step_title' => 'Academic Year Information',
                    'step_number' => 1,
                    'sections' => [
                        [
                            'section_id' => 'section_basic_info',
                            'section_title' => 'Basic Information',
                            'fields' => [
                                [
                                    'field_id' => 'name',
                                    'field_type' => 'text',
                                    'label' => 'Academic Year Name',
                                    'placeholder' => 'e.g., 2024-2025 Academic Year',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:255']
                                ],
                                [
                                    'field_id' => 'year',
                                    'field_type' => 'text',
                                    'label' => 'Year',
                                    'placeholder' => 'e.g., 2024-2025',
                                    'required' => true,
                                    'validation' => ['required', 'string', 'max:10']
                                ],
                                [
                                    'field_id' => 'description',
                                    'field_type' => 'textarea',
                                    'label' => 'Description',
                                    'placeholder' => 'Enter a description for this academic year',
                                    'required' => false,
                                    'validation' => ['nullable', 'string', 'max:1000']
                                ]
                            ]
                        ],
                        [
                            'section_id' => 'section_dates',
                            'section_title' => 'Important Dates',
                            'fields' => [
                                [
                                    'field_id' => 'start_date',
                                    'field_type' => 'date',
                                    'label' => 'Start Date',
                                    'required' => true,
                                    'validation' => ['required', 'date']
                                ],
                                [
                                    'field_id' => 'end_date',
                                    'field_type' => 'date',
                                    'label' => 'End Date',
                                    'required' => true,
                                    'validation' => ['required', 'date', 'after:start_date']
                                ],
                                [
                                    'field_id' => 'enrollment_start_date',
                                    'field_type' => 'date',
                                    'label' => 'Enrollment Start Date',
                                    'required' => false,
                                    'validation' => ['nullable', 'date', 'after_or_equal:start_date']
                                ],
                                [
                                    'field_id' => 'enrollment_end_date',
                                    'field_type' => 'date',
                                    'label' => 'Enrollment End Date',
                                    'required' => false,
                                    'validation' => ['nullable', 'date', 'before:end_date']
                                ]
                            ]
                        ],
                        [
                            'section_id' => 'section_settings',
                            'section_title' => 'Settings',
                            'fields' => [
                                [
                                    'field_id' => 'term_structure',
                                    'field_type' => 'select',
                                    'label' => 'Term Structure',
                                    'required' => false,
                                    'options' => [
                                        ['value' => 'semesters', 'label' => 'Semesters'],
                                        ['value' => 'trimesters', 'label' => 'Trimesters'],
                                        ['value' => 'quarters', 'label' => 'Quarters'],
                                        ['value' => 'year_round', 'label' => 'Year Round']
                                    ],
                                    'validation' => ['nullable', 'in:semesters,trimesters,quarters,year_round']
                                ],
                                [
                                    'field_id' => 'total_terms',
                                    'field_type' => 'number',
                                    'label' => 'Total Terms',
                                    'required' => false,
                                    'validation' => ['nullable', 'integer', 'min:1']
                                ],
                                [
                                    'field_id' => 'is_current',
                                    'field_type' => 'checkbox',
                                    'label' => 'Set as Current Academic Year',
                                    'required' => false,
                                    'validation' => ['nullable', 'boolean']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'validation_rules' => [
                'name' => 'required|string|max:255',
                'year' => 'required|string|max:10',
                'description' => 'nullable|string|max:1000',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'enrollment_start_date' => 'nullable|date',
                'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:end_date',
                'term_structure' => 'nullable|in:semesters,trimesters,quarters,year_round',
                'total_terms' => 'nullable|integer|min:1',
                'is_current' => 'nullable|boolean'
            ],
            'workflow_configuration' => [],
            'metadata' => [
                'auto_created' => true,
                'created_for' => 'academic_year_setup',
                'can_be_customized' => true
            ],
            'created_by' => $userId
        ]);
    }

    /**
     * Display a listing of academic years with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AcademicYear::with([
                'school:id,display_name,school_code',
                'terms:id,academic_year_id,name,start_date,end_date',
                'createdBy:id,name'
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

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('year')) {
                $query->where('year', $request->year);
            }

            if ($request->has('is_current')) {
                $query->where('is_current', $request->boolean('is_current'));
            }

            if ($request->has('date_from')) {
                $query->where('start_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('end_date', '<=', $request->date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('year', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $academicYears = $query->orderBy('year', 'desc')
                ->orderBy('start_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $academicYears->items(),
                'pagination' => [
                    'current_page' => $academicYears->currentPage(),
                    'per_page' => $academicYears->perPage(),
                    'total' => $academicYears->total(),
                    'last_page' => $academicYears->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve academic years',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created academic year with Form Engine processing
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
        $schoolId = $user->school_id;
        if (!$schoolId) {
            $schoolId = $this->getCurrentSchoolId();
            if (!$schoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }
        }

        // Verify school access
        if (!$this->verifySchoolAccess($schoolId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this school'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'year' => 'required|string|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
            'status' => 'required|in:planning,active,completed,archived',
            'enrollment_start_date' => 'nullable|date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:end_date',
            'registration_deadline' => 'nullable|date|before:start_date',
            'term_structure' => 'nullable|in:semesters,trimesters,quarters,year_round',
            'total_terms' => 'nullable|integer|min:1',
            'total_instructional_days' => 'nullable|integer|min:1',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'string|max:255',
            'holidays.*.date' => 'date',
            'holidays.*.type' => 'in:holiday,break,professional_development',
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

            // Check for overlapping academic years (only within same school and tenant, excluding soft deleted)
            // Exclude records with status 'planning' or 'draft' to allow multiple drafts
            // Use withoutTenantScope to ensure we check only the specific tenant_id
            $overlapping = AcademicYear::withoutTenantScope()
                ->where('school_id', $schoolId)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['planning', 'draft']) // Allow overlapping drafts/planning records
                ->where(function($query) use ($request) {
                    $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                          ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                          ->orWhere(function($q) use ($request) {
                              $q->where('start_date', '<=', $request->start_date)
                                ->where('end_date', '>=', $request->end_date);
                          });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Academic year dates overlap with existing academic year'
                ], 422);
            }

            // Check if there's a soft deleted academic year with the same school_id, tenant_id, and year
            $existingDeleted = AcademicYear::withoutTenantScope()
                ->withTrashed()
                ->where('school_id', $schoolId)
                ->where('tenant_id', $tenantId)
                ->where('year', $request->year)
                ->whereNotNull('deleted_at')
                ->first();

            if ($existingDeleted) {
                // Restore and update the existing academic year
                $existingDeleted->restore();
                $academicYearData = $request->except(['form_data', 'holidays']);
                $academicYearData['holidays_json'] = $request->holidays ? json_encode($request->holidays) : null;
                $existingDeleted->update($academicYearData);
                $academicYear = $existingDeleted->fresh();
            } else {
                // Create new academic year
                $academicYearData = $request->except(['form_data', 'holidays']);
                $academicYearData['created_by'] = $user->id;
                $academicYearData['tenant_id'] = $tenantId;
                $academicYearData['school_id'] = $schoolId;
                $academicYearData['holidays_json'] = $request->holidays ? json_encode($request->holidays) : null;

                $academicYear = AcademicYear::create($academicYearData);
            }

            // If this is set as current, update other academic years
            if ($request->is_current) {
                AcademicYear::where('school_id', $schoolId)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                // Ensure form template exists, create if it doesn't
                $this->ensureFormTemplateExists('academic_year_setup', $tenantId, $user->id);

                $processedData = $this->formEngineService->processFormData('academic_year_setup', $request->form_data);
                $this->formEngineService->createFormInstance('academic_year_setup', $processedData, 'AcademicYear', $academicYear->id, $tenantId);
            }

            // Create academic terms automatically whenever term_structure & total_terms are provided.
            // We intentionally allow this even when status is 'planning' so draft years can already
            // have their terms generated.
            \Log::info('AcademicYearController@store: Checking if terms should be created', [
                'academic_year_id' => $academicYear->id,
                'status' => $academicYear->status,
                'term_structure' => $request->term_structure,
                'total_terms' => $request->total_terms,
                'should_create' => $request->filled('term_structure') && $request->filled('total_terms')
            ]);
            
            if ($request->filled('term_structure') && $request->filled('total_terms')) {
                \Log::info('AcademicYearController@store: Creating academic terms automatically', [
                    'academic_year_id' => $academicYear->id,
                    'term_structure' => $request->term_structure,
                    'total_terms' => $request->total_terms
                ]);
                $this->createAcademicTermsAutomatically($academicYear, $request, $user, $tenantId);
            } else {
                \Log::warning('AcademicYearController@store: Terms NOT created', [
                    'reason' => 'Status is planning or missing term_structure/total_terms',
                    'status' => $academicYear->status,
                    'has_term_structure' => $request->filled('term_structure'),
                    'has_total_terms' => $request->filled('total_terms')
                ]);
            }

            // Start academic year setup workflow
            $workflow = $this->workflowService->startWorkflow($academicYear, 'academic_year_setup');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic year created successfully',
                'data' => [
                    'academic_year' => $academicYear->load(['school:id,display_name,school_code']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified academic year
     */
    public function show(AcademicYear $academicYear): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            $academicYear->load([
                'school:id,display_name,school_code,school_type',
                'terms:id,academic_year_id,name,start_date,end_date,status',
                'createdBy:id,name',
                'students:id,first_name,last_name,current_grade_level,enrollment_status'
            ]);

            // Get academic year statistics
            $stats = [
                'total_students' => $academicYear->students()->count(),
                'active_students' => $academicYear->students()->where('enrollment_status', 'enrolled')->count(),
                'by_grade_level' => $academicYear->students()
                    ->selectRaw('current_grade_level, COUNT(*) as count')
                    ->groupBy('current_grade_level')
                    ->get(),
                'terms_count' => $academicYear->terms()->count(),
                'active_terms' => $academicYear->terms()->where('status', 'active')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'academic_year' => $academicYear,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified academic year
     */
    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        // Verify access: must belong to user's school
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this academic year'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'year' => 'sometimes|required|string|max:10',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
            'status' => 'sometimes|required|in:planning,active,completed,archived',
            'enrollment_start_date' => 'nullable|date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:end_date',
            'registration_deadline' => 'nullable|date|before:start_date',
            'term_structure' => 'nullable|in:semesters,trimesters,quarters,year_round',
            'total_terms' => 'nullable|integer|min:1',
            'total_instructional_days' => 'nullable|integer|min:1',
            'holidays' => 'nullable|array',
            'holidays.*.name' => 'string|max:255',
            'holidays.*.date' => 'date',
            'holidays.*.type' => 'in:holiday,break,professional_development',
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

            // Check for overlapping academic years when updating dates (exclude the current record)
            if ($request->filled('start_date') || $request->filled('end_date')) {
                $startDate = $request->start_date ?? $academicYear->start_date;
                $endDate = $request->end_date ?? $academicYear->end_date;
                
                $overlapping = AcademicYear::withoutTenantScope()
                    ->where('school_id', $academicYear->school_id)
                    ->where('tenant_id', $academicYear->tenant_id)
                    ->where('id', '!=', $academicYear->id) // Exclude the current record
                    ->whereNotIn('status', ['planning', 'draft']) // Allow overlapping drafts/planning records
                    ->where(function($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                              ->orWhereBetween('end_date', [$startDate, $endDate])
                              ->orWhere(function($q) use ($startDate, $endDate) {
                                  $q->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                              });
                    })
                    ->exists();

                if ($overlapping) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Academic year dates overlap with existing academic year'
                    ], 422);
                }
            }

            $updateData = $request->except(['holidays']);

            if ($request->has('holidays')) {
                $updateData['holidays_json'] = json_encode($request->holidays);
            }

            // If this is set as current, update other academic years
            if ($request->is_current) {
                AcademicYear::where('school_id', $academicYear->school_id)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }

            $academicYear->update($updateData);

            // Create academic terms automatically if status changed to not 'planning' and terms don't exist
            $termsCount = $academicYear->terms()->count();
            \Log::info('AcademicYearController@update: Checking if terms should be created', [
                'academic_year_id' => $academicYear->id,
                'current_status' => $academicYear->status,
                'new_status' => $request->status,
                'terms_count' => $termsCount,
                'has_term_structure' => $request->filled('term_structure'),
                'has_total_terms' => $request->filled('total_terms'),
                'should_create' => $request->filled('status') && $request->status !== 'planning' && $termsCount === 0 && $request->filled('term_structure') && $request->filled('total_terms')
            ]);
            
            if ($request->filled('status') && $request->status !== 'planning' && $termsCount === 0) {
                if ($request->filled('term_structure') && $request->filled('total_terms')) {
                    $user = auth('api')->user();
                    $tenantId = $this->getCurrentTenantId();
                    \Log::info('AcademicYearController@update: Creating academic terms automatically');
                    $this->createAcademicTermsAutomatically($academicYear, $request, $user, $tenantId);
                } else {
                    \Log::warning('AcademicYearController@update: Terms NOT created - missing term_structure or total_terms');
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic year updated successfully',
                'data' => $academicYear->fresh()->load(['school:id,display_name,school_code'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified academic year
     */
    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            DB::beginTransaction();

            // Check if academic year has enrolled students
            $enrolledStudents = $academicYear->students()->where('enrollment_status', 'enrolled')->count();
            if ($enrolledStudents > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete academic year with {$enrolledStudents} enrolled students"
                ], 422);
            }

            // Check if it's the current academic year
            if ($academicYear->is_current) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete current academic year'
                ], 422);
            }

            // Soft delete academic year
            $academicYear->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic year deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic years by school
     */
    public function getBySchool(int $schoolId): JsonResponse
    {
        try {
            // Verify access to this school
            if (!$this->verifySchoolAccess($schoolId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }

            $academicYears = AcademicYear::where('school_id', $schoolId)
                ->with(['terms:id,academic_year_id,name,start_date,end_date,status'])
                ->orderBy('year', 'desc')
                ->orderBy('start_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $academicYears
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve school academic years',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current academic year for a school
     */
    public function getCurrent(?int $schoolId = null): JsonResponse
    {
        try {
            // If no school_id provided, use user's school_id
            if (!$schoolId) {
                $schoolId = $this->getCurrentSchoolId();
                if (!$schoolId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not associated with any school'
                    ], 403);
                }
            }

            // Verify access to this school
            if (!$this->verifySchoolAccess($schoolId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }

            // First, try to find academic year with is_current = true
            $currentYear = AcademicYear::where('school_id', $schoolId)
                ->where('is_current', true)
                ->with(['terms:id,academic_year_id,name,start_date,end_date,status'])
                ->first();

            // If no current year found by is_current flag, try to find active year that contains today's date
            if (!$currentYear) {
                $today = now();
                $currentYear = AcademicYear::where('school_id', $schoolId)
                    ->where('status', 'active')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->with(['terms:id,academic_year_id,name,start_date,end_date,status'])
                    ->orderBy('start_date', 'desc')
                    ->first();
            }

            // If still no current year, try to find the most recent active or planning year
            if (!$currentYear) {
                $currentYear = AcademicYear::where('school_id', $schoolId)
                    ->whereIn('status', ['active', 'planning'])
                    ->with(['terms:id,academic_year_id,name,start_date,end_date,status'])
                    ->orderBy('start_date', 'desc')
                    ->first();
            }

            if (!$currentYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No current academic year found for this school'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $currentYear
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set academic year as current
     */
    public function setAsCurrent(AcademicYear $academicYear): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            DB::beginTransaction();

            // Update other academic years to not current
            AcademicYear::where('school_id', $academicYear->school_id)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_current' => false]);

            // Set this academic year as current
            $academicYear->update(['is_current' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic year set as current successfully',
                'data' => $academicYear->fresh()->load(['school:id,official_name,school_code'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set academic year as current',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic year calendar
     */
    public function getCalendar(AcademicYear $academicYear): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            $calendar = [
                'academic_year' => $academicYear->only(['id', 'name', 'year', 'start_date', 'end_date']),
                'terms' => $academicYear->terms()
                    ->select('id', 'name', 'start_date', 'end_date', 'status')
                    ->orderBy('start_date')
                    ->get(),
                'holidays' => $academicYear->holidays_json ? json_decode($academicYear->holidays_json, true) : [],
                'important_dates' => [
                    'enrollment_start' => $academicYear->enrollment_start_date,
                    'enrollment_end' => $academicYear->enrollment_end_date,
                    'registration_deadline' => $academicYear->registration_deadline
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $calendar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic year calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic terms for a specific academic year
     */
    public function getTerms(AcademicYear $academicYear): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicYear->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            $terms = $academicYear->terms()
                ->select('id', 'name', 'type', 'term_number', 'start_date', 'end_date', 'status', 'is_current')
                ->orderBy('term_number')
                ->orderBy('start_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $terms
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve academic terms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create academic years
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $request->tenant_id ?? $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        // Get school_id from user if not provided
        $schoolId = $request->school_id;
        if (!$schoolId) {
            $schoolId = $this->getCurrentSchoolId();
            if (!$schoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }
        }

        // Verify school access
        if (!$this->verifySchoolAccess($schoolId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this school'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'academic_years' => 'required|array|min:1',
            'academic_years.*.name' => 'required|string|max:255',
            'academic_years.*.year' => 'required|string|max:10',
            'academic_years.*.start_date' => 'required|date',
            'academic_years.*.end_date' => 'required|date|after:academic_years.*.start_date',
            'academic_years.*.description' => 'nullable|string|max:1000',
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

            $createdCount = 0;
            $academicYears = [];

            foreach ($request->academic_years as $yearData) {
                // Check for overlapping dates (only within same school and tenant, excluding soft deleted)
                // Use withoutTenantScope to ensure we check only the specific tenant_id
                $overlapping = AcademicYear::withoutTenantScope()
                    ->where('school_id', $schoolId)
                    ->where('tenant_id', $tenantId)
                    ->where(function($query) use ($yearData) {
                        $query->whereBetween('start_date', [$yearData['start_date'], $yearData['end_date']])
                              ->orWhereBetween('end_date', [$yearData['start_date'], $yearData['end_date']])
                              ->orWhere(function($q) use ($yearData) {
                                  $q->where('start_date', '<=', $yearData['start_date'])
                                    ->where('end_date', '>=', $yearData['end_date']);
                              });
                    })
                    ->exists();

                if (!$overlapping) {
                    // Check if there's a soft deleted academic year with the same school_id, tenant_id, and year
                    $existingDeleted = AcademicYear::withoutTenantScope()
                        ->withTrashed()
                        ->where('school_id', $schoolId)
                        ->where('tenant_id', $tenantId)
                        ->where('year', $yearData['year'])
                        ->whereNotNull('deleted_at')
                        ->first();

                    if ($existingDeleted) {
                        // Restore and update the existing academic year
                        $existingDeleted->restore();
                        $existingDeleted->update([
                            'name' => $yearData['name'],
                            'start_date' => $yearData['start_date'],
                            'end_date' => $yearData['end_date'],
                            'description' => $yearData['description'] ?? $existingDeleted->description,
                            'status' => 'planning',
                            'is_current' => false,
                        ]);
                        $academicYear = $existingDeleted->fresh();
                    } else {
                        // Create new academic year
                        $academicYear = AcademicYear::create([
                            'school_id' => $schoolId,
                            'name' => $yearData['name'],
                            'year' => $yearData['year'],
                            'start_date' => $yearData['start_date'],
                            'end_date' => $yearData['end_date'],
                            'description' => $yearData['description'] ?? null,
                            'status' => 'planning',
                            'is_current' => false,
                            'created_by' => $user->id,
                            'tenant_id' => $tenantId
                        ]);
                    }

                    $academicYears[] = $academicYear;
                    $createdCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$createdCount} academic years",
                'data' => [
                    'created_count' => $createdCount,
                    'academic_years' => $academicYears
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create academic years',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic year statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $query = AcademicYear::query();

            // Filter by school_id
            $userSchoolId = $this->getCurrentSchoolId();
            if ($userSchoolId) {
                $query->where('school_id', $userSchoolId);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }

            $stats = [
                'total_academic_years' => (clone $query)->count(),
                'by_status' => (clone $query)->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_school' => (clone $query)->selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,official_name')
                    ->groupBy('school_id')
                    ->get(),
                'current_years' => (clone $query)->where('is_current', true)->count(),
                'active_years' => (clone $query)->where('status', 'active')->count(),
                'recent_years' => (clone $query)->where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic year statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic year trends
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $schoolId = $request->get('school_id');
            $years = $request->get('years', 5);

            // If no school_id provided, use user's school_id
            if (!$schoolId) {
                $schoolId = $this->getCurrentSchoolId();
                if (!$schoolId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not associated with any school'
                    ], 403);
                }
            }

            // Verify school access
            if (!$this->verifySchoolAccess($schoolId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this school'
                ], 403);
            }

            $query = AcademicYear::selectRaw('year, COUNT(*) as count, AVG(DATEDIFF(end_date, start_date)) as avg_duration')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->limit($years);

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            $trends = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'school_id' => $schoolId,
                    'years_analyzed' => $years,
                    'trends' => $trends
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic year trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search academic years by year with various filtering options
     */
    public function searchByYear(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year' => 'required|string',
                'school_id' => 'nullable|exists:schools,id',
                'tenant_id' => 'nullable|exists:tenants,id',
                'status' => 'nullable|in:planning,active,completed,archived',
                'is_current' => 'nullable|boolean',
                'exact_match' => 'nullable|boolean',
                'include_terms' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = AcademicYear::query();

            // Apply year filter
            if ($request->boolean('exact_match', false)) {
                $query->where('year', $request->year);
            } else {
                $query->where('year', 'like', "%{$request->year}%");
            }

            // Apply school_id filter
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

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('is_current')) {
                $query->where('is_current', $request->boolean('is_current'));
            }

            // Load relationships based on request
            $with = ['school:id,display_name,school_code', 'createdBy:id,name'];

            if ($request->boolean('include_terms', false)) {
                $with[] = 'terms:id,academic_year_id,name,start_date,end_date,status';
            }

            $query->with($with);

            // Order by year and start date
            $academicYears = $query->orderBy('year', 'desc')
                ->orderBy('start_date', 'desc')
                ->paginate($request->get('per_page', 15));

            // Add search metadata
            $searchMetadata = [
                'search_year' => $request->year,
                'exact_match' => $request->boolean('exact_match', false),
                'total_results' => $academicYears->total(),
                'filters_applied' => [
                    'school_id' => $request->school_id,
                    'tenant_id' => $request->tenant_id,
                    'status' => $request->status,
                    'is_current' => $request->is_current
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $academicYears->items(),
                'pagination' => [
                    'current_page' => $academicYears->currentPage(),
                    'per_page' => $academicYears->perPage(),
                    'total' => $academicYears->total(),
                    'last_page' => $academicYears->lastPage()
                ],
                'search_metadata' => $searchMetadata
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search academic years by year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create academic terms automatically based on term structure
     */
    protected function createAcademicTermsAutomatically(AcademicYear $academicYear, Request $request, $user, $tenantId): void
    {
        \Log::info('AcademicYearController@createAcademicTermsAutomatically: Starting', [
            'academic_year_id' => $academicYear->id,
            'academic_year_name' => $academicYear->name,
            'term_structure' => $request->term_structure,
            'total_terms' => $request->total_terms,
            'start_date' => $academicYear->start_date,
            'end_date' => $academicYear->end_date,
            'tenant_id' => $tenantId,
            'school_id' => $academicYear->school_id
        ]);
        
        $termStructure = $request->term_structure;
        $totalTerms = $request->total_terms ?? 2;
        $startDate = Carbon::parse($academicYear->start_date);
        $endDate = Carbon::parse($academicYear->end_date);
        
        // Use startDate->diffInDays(endDate) to guarantee a positive duration
        $totalDays = $startDate->diffInDays($endDate) + 1;

        \Log::info('AcademicYearController@createAcademicTermsAutomatically: Calculated dates', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_days' => $totalDays
        ]);

        // Calculate term dates
        $daysPerTerm = floor($totalDays / $totalTerms);

        $termLabels = [
            'semesters' => ['1º Semestre', '2º Semestre'],
            'trimesters' => ['1º Trimestre', '2º Trimestre', '3º Trimestre'],
            'quarters' => ['1º Quadrimestre', '2º Quadrimestre', '3º Quadrimestre', '4º Quadrimestre'],
            'year_round' => ['Ano Letivo']
        ];

        $labels = $termLabels[$termStructure] ?? array_map(fn($i) => "{$i}º Período", range(1, $totalTerms));

        // Map term structure to type
        $typeMap = [
            'semesters' => 'semester',
            'trimesters' => 'trimester',
            'quarters' => 'quarter',
            'year_round' => 'other'
        ];
        $termType = $typeMap[$termStructure] ?? 'other';

        $currentStart = clone $startDate;
        $createdCount = 0;
        $skippedCount = 0;

        \Log::info('AcademicYearController@createAcademicTermsAutomatically: Pre-loop summary', [
            'total_terms_requested' => $totalTerms,
            'term_structure' => $termStructure,
            'term_type' => $termType,
            'days_per_term' => $daysPerTerm,
            'existing_terms_count' => AcademicTerm::where('academic_year_id', $academicYear->id)->count(),
        ]);

        \Log::info('AcademicYearController@createAcademicTermsAutomatically: Starting loop', [
            'total_terms' => $totalTerms,
            'days_per_term' => $daysPerTerm,
            'term_type' => $termType
        ]);

        for ($i = 0; $i < $totalTerms; $i++) {
            $termStart = clone $currentStart;
            
            if ($i === $totalTerms - 1) {
                // Last term ends on the academic year end date
                $termEnd = clone $endDate;
            } else {
                // Calculate end date for this term
                $termEnd = clone $termStart;
                $termEnd->addDays($daysPerTerm - 1);
                
                // Ensure we don't exceed the academic year end date
                if ($termEnd > $endDate) {
                    $termEnd = clone $endDate;
                }
            }

            \Log::info("AcademicYearController@createAcademicTermsAutomatically: Processing term {$i}", [
                'term_number' => $i + 1,
                'term_start' => $termStart->format('Y-m-d'),
                'term_end' => $termEnd->format('Y-m-d'),
                'term_name' => $labels[$i] ?? "{$i}º Período"
            ]);

            // Check for overlapping terms
            $overlapping = AcademicTerm::withoutTenantScope()
                ->where('academic_year_id', $academicYear->id)
                ->where('tenant_id', $tenantId)
                ->where(function($query) use ($termStart, $termEnd) {
                    $query->whereBetween('start_date', [$termStart, $termEnd])
                          ->orWhereBetween('end_date', [$termStart, $termEnd])
                          ->orWhere(function($q) use ($termStart, $termEnd) {
                              $q->where('start_date', '<=', $termStart)
                                ->where('end_date', '>=', $termEnd);
                          });
                })
                ->exists();

            if (!$overlapping) {
                try {
                    $term = AcademicTerm::create([
                        'academic_year_id' => $academicYear->id,
                        'school_id' => $academicYear->school_id,
                        'name' => $labels[$i] ?? "{$i}º Período",
                        'type' => $termType,
                        'term_number' => $i + 1,
                        'start_date' => $termStart->format('Y-m-d'),
                        'end_date' => $termEnd->format('Y-m-d'),
                        'description' => ($labels[$i] ?? "{$i}º Período") . ' do ano letivo',
                        'status' => 'planning',
                        'is_current' => false,
                        'created_by' => $user->id,
                        'tenant_id' => $tenantId
                    ]);
                    
                    $createdCount++;
                    \Log::info("AcademicYearController@createAcademicTermsAutomatically: Term created successfully", [
                        'term_id' => $term->id,
                        'term_name' => $term->name,
                        'term_number' => $term->term_number,
                        'academic_year_id' => $academicYear->id,
                        'current_total_terms_in_db' => AcademicTerm::where('academic_year_id', $academicYear->id)->count(),
                    ]);
                } catch (\Exception $e) {
                    \Log::error("AcademicYearController@createAcademicTermsAutomatically: Failed to create term {$i}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                $skippedCount++;
                \Log::warning("AcademicYearController@createAcademicTermsAutomatically: Term {$i} skipped (overlapping)", [
                    'term_number' => $i + 1,
                    'term_start' => $termStart->format('Y-m-d'),
                    'term_end' => $termEnd->format('Y-m-d')
                ]);
            }

            // Next term starts the day after this term ends
            if ($i < $totalTerms - 1) {
                $currentStart = clone $termEnd;
                $currentStart->addDay();
            }
        }
        
        \Log::info('AcademicYearController@createAcademicTermsAutomatically: Completed', [
            'academic_year_id' => $academicYear->id,
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'total_expected' => $totalTerms,
            'final_terms_in_db' => AcademicTerm::where('academic_year_id', $academicYear->id)->get(['id','term_number','name','start_date','end_date'])->toArray(),
        ]);
    }
}

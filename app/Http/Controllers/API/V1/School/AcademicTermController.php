<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AcademicTermController extends Controller
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
     * Display a listing of academic terms with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AcademicTerm::with([
                'academicYear:id,name,year,school_id',
                // schools table uses display_name and school_code
                'academicYear.school:id,display_name,school_code',
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

            // Apply filters
            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
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
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $academicTerms = $query->orderBy('start_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $academicTerms->items(),
                'pagination' => [
                    'current_page' => $academicTerms->currentPage(),
                    'per_page' => $academicTerms->perPage(),
                    'total' => $academicTerms->total(),
                    'last_page' => $academicTerms->lastPage()
                ]
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
     * Store a newly created academic term with Form Engine processing
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

        // Get academic year and verify access
        if (!$request->has('academic_year_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Academic year ID is required'
            ], 422);
        }

        $academicYear = AcademicYear::findOrFail($request->academic_year_id);

        // Verify access to the academic year's school
        if (!$this->verifySchoolAccess($academicYear->school_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this academic year'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:semester,quarter,trimester,other',
            'term_number' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:planning,active,completed,archived',
            'is_current' => 'boolean',
            'enrollment_start_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_end_date' => 'nullable|date|before:end_date',
            'registration_deadline' => 'nullable|date|before:start_date',
            'grades_due_date' => 'nullable|date|before:end_date',
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

            // Check for overlapping terms within the same academic year and tenant
            // Use withoutTenantScope to ensure we check only the specific tenant_id
            $overlapping = AcademicTerm::withoutTenantScope()
                ->where('academic_year_id', $request->academic_year_id)
                ->where('tenant_id', $tenantId)
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
                    'message' => 'Term dates overlap with existing term in this academic year'
                ], 422);
            }

            // Check if there's a soft deleted academic term with the same academic_year_id, tenant_id, and name
            $existingDeleted = AcademicTerm::withoutTenantScope()
                ->withTrashed()
                ->where('academic_year_id', $request->academic_year_id)
                ->where('tenant_id', $tenantId)
                ->where('name', $request->name)
                ->whereNotNull('deleted_at')
                ->first();

            // Auto-generate term_number if not provided
            if (!$request->has('term_number') || is_null($request->term_number)) {
                $maxTermNumber = AcademicTerm::withoutTenantScope()
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('tenant_id', $tenantId)
                    ->max('term_number') ?? 0;
                $request->merge(['term_number' => $maxTermNumber + 1]);
            }

            if ($existingDeleted) {
                // Restore and update the existing academic term
                $existingDeleted->restore();
                $termData = $request->except(['form_data', 'holidays']);
                $termData['holidays_json'] = $request->holidays ? json_encode($request->holidays) : null;
                $existingDeleted->update($termData);
                $academicTerm = $existingDeleted->fresh();
            } else {
                // Create academic term
                $termData = $request->except(['form_data', 'holidays']);
                $termData['created_by'] = $user->id;
                $termData['tenant_id'] = $tenantId;
                $termData['school_id'] = $academicYear->school_id;
                $termData['holidays_json'] = $request->holidays ? json_encode($request->holidays) : null;

                $academicTerm = AcademicTerm::create($termData);
            }

            // If this is set as current, update other terms in the same academic year
            if ($request->is_current) {
                AcademicTerm::where('academic_year_id', $request->academic_year_id)
                    ->where('id', '!=', $academicTerm->id)
                    ->update(['is_current' => false]);
            }

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('academic_year_setup', $request->form_data);
                $this->formEngineService->createFormInstance('academic_year_setup', $processedData, 'AcademicTerm', $academicTerm->id, $academicTerm->tenant_id);
            }

            // Start term setup workflow
            $workflow = $this->workflowService->startWorkflow($academicTerm, 'term_setup', [
                'steps' => [
                    [
                        'step_number' => 1,
                        'step_name' => 'Initial Setup',
                        'step_type' => 'review',
                        'required_role' => 'administrator',
                        'instructions' => 'Complete initial term setup and configuration'
                    ],
                    [
                        'step_number' => 2,
                        'step_name' => 'Curriculum Planning',
                        'step_type' => 'review',
                        'required_role' => 'curriculum_coordinator',
                        'instructions' => 'Review and approve curriculum for the term'
                    ],
                    [
                        'step_number' => 3,
                        'step_name' => 'Staff Assignment',
                        'step_type' => 'review',
                        'required_role' => 'principal',
                        'instructions' => 'Assign staff and teachers to classes'
                    ],
                    [
                        'step_number' => 4,
                        'step_name' => 'Schedule Setup',
                        'step_type' => 'review',
                        'required_role' => 'scheduler',
                        'instructions' => 'Create and finalize class schedules'
                    ],
                    [
                        'step_number' => 5,
                        'step_name' => 'Final Approval',
                        'step_type' => 'approval',
                        'required_role' => 'superintendent',
                        'instructions' => 'Final approval to activate the academic term'
                    ]
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic term created successfully',
                'data' => [
                    'academic_term' => $academicTerm->load(['academicYear:id,name,year']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create academic term',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified academic term
     */
    public function show(AcademicTerm $academicTerm): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicTerm->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic term'
                ], 403);
            }

            $academicTerm->load([
                'academicYear:id,name,year,school_id',
                'academicYear.school:id,display_name,school_code',
                'createdBy:id,name'
            ]);

            // Get term statistics (simplified for now)
            $stats = [
                'total_students' => 0,
                'active_students' => 0,
                'by_grade_level' => []
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'academic_term' => $academicTerm,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve academic term',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified academic term
     */
    public function update(Request $request, AcademicTerm $academicTerm): JsonResponse
    {
        // Verify access: must belong to user's school
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId || $academicTerm->school_id != $userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this academic term'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:semester,quarter,trimester,other',
            'term_number' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:planning,active,completed,archived',
            'is_current' => 'boolean',
            'enrollment_start_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_end_date' => 'nullable|date|before:end_date',
            'registration_deadline' => 'nullable|date|before:start_date',
            'grades_due_date' => 'nullable|date|before:end_date',
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

            // Check for overlapping terms if dates are being updated
            if ($request->has('start_date') || $request->has('end_date')) {
                $startDate = $request->start_date ?? $academicTerm->start_date;
                $endDate = $request->end_date ?? $academicTerm->end_date;

                $overlapping = AcademicTerm::withoutTenantScope()
                    ->where('academic_year_id', $academicTerm->academic_year_id)
                    ->where('tenant_id', $academicTerm->tenant_id)
                    ->where('id', '!=', $academicTerm->id)
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Term dates overlap with existing term in this academic year'
                    ], 422);
                }
            }

            $updateData = $request->except(['holidays']);

            if ($request->has('holidays')) {
                $updateData['holidays_json'] = json_encode($request->holidays);
            }

            // If this is set as current, update other terms in the same academic year
            if ($request->is_current) {
                AcademicTerm::where('academic_year_id', $academicTerm->academic_year_id)
                    ->where('id', '!=', $academicTerm->id)
                    ->update(['is_current' => false]);
            }

            $academicTerm->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic term updated successfully',
                'data' => $academicTerm->fresh()->load(['academicYear:id,name,year'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update academic term',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified academic term
     */
    public function destroy(AcademicTerm $academicTerm): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicTerm->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic term'
                ], 403);
            }

            DB::beginTransaction();

            // Check if it's the current term
            if ($academicTerm->is_current) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete current academic term'
                ], 422);
            }

            // Soft delete academic term
            $academicTerm->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic term deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete academic term',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic terms by academic year
     */
    public function getByAcademicYear(int $academicYearId): JsonResponse
    {
        try {
            // Get academic year and verify access
            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Verify access to this school
            if (!$this->verifySchoolAccess($academicYear->school_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            $academicTerms = AcademicTerm::where('academic_year_id', $academicYearId)
                ->orderBy('start_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $academicTerms
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve academic year terms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current academic term for a school
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

            $currentTerm = AcademicTerm::whereHas('academicYear', function($query) use ($schoolId) {
                    $query->where('school_id', $schoolId);
                })
                ->where('is_current', true)
                ->with(['academicYear:id,name,year'])
                ->first();

            if (!$currentTerm) {
                return response()->json([
                    'success' => false,
                    'message' => 'No current academic term found for this school'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $currentTerm
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current academic term',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set academic term as current
     */
    public function setAsCurrent(AcademicTerm $academicTerm): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicTerm->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic term'
                ], 403);
            }

            DB::beginTransaction();

            // Update other terms in the same academic year to not current
            AcademicTerm::where('academic_year_id', $academicTerm->academic_year_id)
                ->where('id', '!=', $academicTerm->id)
                ->update(['is_current' => false]);

            // Set this term as current
            $academicTerm->update(['is_current' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Academic term set as current successfully',
                'data' => $academicTerm->fresh()->load(['academicYear:id,name,year'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set academic term as current',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic term calendar
     */
    public function getCalendar(AcademicTerm $academicTerm): JsonResponse
    {
        try {
            // Verify access: must belong to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId || $academicTerm->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic term'
                ], 403);
            }

            $calendar = [
                'academic_term' => $academicTerm->only(['id', 'name', 'type', 'start_date', 'end_date']),
                'academic_year' => $academicTerm->academicYear->only(['id', 'name', 'year']),
                'important_dates' => [
                    'enrollment_start' => $academicTerm->enrollment_start_date,
                    'enrollment_end' => $academicTerm->enrollment_end_date,
                    'registration_deadline' => $academicTerm->registration_deadline,
                    'grades_due_date' => $academicTerm->grades_due_date
                ],
                'holidays' => $academicTerm->holidays_json ?? []
            ];

            return response()->json([
                'success' => true,
                'data' => $calendar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic term calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create academic terms
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
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
            'academic_terms' => 'required|array|min:1',
            'academic_terms.*.name' => 'required|string|max:255',
            'academic_terms.*.type' => 'required|in:semester,quarter,trimester,other',
            'academic_terms.*.term_number' => 'nullable|integer|min:1',
            'academic_terms.*.start_date' => 'required|date',
            'academic_terms.*.end_date' => 'required|date|after:academic_terms.*.start_date',
            'academic_terms.*.description' => 'nullable|string|max:1000',
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

            // Get school_id from academic year and verify access
            $academicYear = AcademicYear::findOrFail($request->academic_year_id);

            // Verify access to this school
            if (!$this->verifySchoolAccess($academicYear->school_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this academic year'
                ], 403);
            }

            $createdCount = 0;
            $academicTerms = [];
            $currentTermNumber = AcademicTerm::withoutTenantScope()
                ->where('academic_year_id', $request->academic_year_id)
                ->where('tenant_id', $tenantId)
                ->max('term_number') ?? 0;

            foreach ($request->academic_terms as $termData) {
                // Auto-generate term_number if not provided
                if (!isset($termData['term_number']) || is_null($termData['term_number'])) {
                    $currentTermNumber++;
                    $termData['term_number'] = $currentTermNumber;
                }

                // Check for overlapping dates within the same academic year and tenant
                // Use withoutTenantScope to ensure we check only the specific tenant_id
                $overlapping = AcademicTerm::withoutTenantScope()
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('tenant_id', $tenantId)
                    ->where(function($query) use ($termData) {
                        $query->whereBetween('start_date', [$termData['start_date'], $termData['end_date']])
                              ->orWhereBetween('end_date', [$termData['start_date'], $termData['end_date']])
                              ->orWhere(function($q) use ($termData) {
                                  $q->where('start_date', '<=', $termData['start_date'])
                                    ->where('end_date', '>=', $termData['end_date']);
                              });
                    })
                    ->exists();

                if (!$overlapping) {
                    // Check if there's a soft deleted academic term with the same academic_year_id, tenant_id, and name
                    $existingDeleted = AcademicTerm::withoutTenantScope()
                        ->withTrashed()
                        ->where('academic_year_id', $request->academic_year_id)
                        ->where('tenant_id', $tenantId)
                        ->where('name', $termData['name'])
                        ->whereNotNull('deleted_at')
                        ->first();

                    if ($existingDeleted) {
                        // Restore and update the existing academic term
                        $existingDeleted->restore();
                        $existingDeleted->update([
                            'type' => $termData['type'],
                            'term_number' => $termData['term_number'] ?? null,
                            'start_date' => $termData['start_date'],
                            'end_date' => $termData['end_date'],
                            'description' => $termData['description'] ?? $existingDeleted->description,
                            'status' => 'planning',
                            'is_current' => false,
                        ]);
                        $academicTerm = $existingDeleted->fresh();
                    } else {
                        // Create new academic term
                        $academicTerm = AcademicTerm::create([
                            'academic_year_id' => $request->academic_year_id,
                            'school_id' => $academicYear->school_id,
                            'name' => $termData['name'],
                            'type' => $termData['type'],
                            'term_number' => $termData['term_number'] ?? null,
                            'start_date' => $termData['start_date'],
                            'end_date' => $termData['end_date'],
                            'description' => $termData['description'] ?? null,
                            'status' => 'planning',
                            'is_current' => false,
                            'created_by' => $user->id,
                            'tenant_id' => $tenantId
                        ]);
                    }

                    $academicTerms[] = $academicTerm;
                    $createdCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$createdCount} academic terms",
                'data' => [
                    'created_count' => $createdCount,
                    'academic_terms' => $academicTerms
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create academic terms',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic term statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $query = AcademicTerm::query();

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
                'total_academic_terms' => (clone $query)->count(),
                'by_status' => (clone $query)->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_type' => (clone $query)->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->get(),
                'by_academic_year' => (clone $query)->selectRaw('academic_year_id, COUNT(*) as count')
                    ->with('academicYear:id,name,year')
                    ->groupBy('academic_year_id')
                    ->get(),
                'current_terms' => (clone $query)->where('is_current', true)->count(),
                'active_terms' => (clone $query)->where('status', 'active')->count(),
                'recent_terms' => (clone $query)->where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic term statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic term trends
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $academicYearId = $request->get('academic_year_id');
            $schoolId = $request->get('school_id');

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

            $query = AcademicTerm::selectRaw('type, COUNT(*) as count, AVG(DATEDIFF(end_date, start_date)) as avg_duration')
                ->where('school_id', $schoolId)
                ->groupBy('type');

            if ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            }

            $trends = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'academic_year_id' => $academicYearId,
                    'school_id' => $schoolId,
                    'trends' => $trends
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic term trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

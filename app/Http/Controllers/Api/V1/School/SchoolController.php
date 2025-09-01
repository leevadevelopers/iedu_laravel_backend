<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\School\School;
use App\Models\Forms\FormTemplate;
use App\Services\SchoolService;
use App\Http\Requests\School\CreateSchoolRequest;
use App\Http\Requests\School\UpdateSchoolRequest;
use App\Http\Requests\School\CreateFormTemplateRequest;
use App\Http\Requests\School\UpdateFormTemplateRequest;
use App\Http\Requests\School\ProcessFormSubmissionRequest;
use App\Http\Requests\School\UpdateFormInstanceStatusRequest;
use App\Http\Requests\School\DuplicateFormTemplateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SchoolController extends Controller
{
    protected $schoolService;

    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'School API is healthy',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Display a listing of schools with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'school_type', 'state_province', 'country_code', 'search']);
            $perPage = $request->get('per_page', 15);

            // Create cache key based on filters and user
            $user = Auth::user();
            $cacheKey = 'schools_' . md5(serialize($filters) . $perPage . ($user ? $user->id : 'guest'));

            // Track cache keys for invalidation
            $cacheKeys = Cache::get('schools_cache_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('schools_cache_keys', $cacheKeys, 3600); // Store for 1 hour
            }

            // Try to get from cache first
            $schools = Cache::remember($cacheKey, 300, function () use ($filters, $perPage) {
                return $this->schoolService->getSchools($filters, $perPage);
            });

            return response()->json([
                'success' => true,
                'data' => $schools
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schools',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created school with Form Engine processing
     */
    public function store(CreateSchoolRequest $request): JsonResponse
    {
        try {
            $result = $this->schoolService->createSchool($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'School created successfully',
                'data' => [
                    'school' => $result['school']->only(['id', 'official_name', 'school_code', 'school_type', 'status', 'created_at']),
                    'workflow_id' => $result['workflow']?->id
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create school',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified school
     */
    public function show(School $school): JsonResponse
    {
        try {
            $school->load([
                'academicYears:id,name,start_date,end_date,status',
                'currentAcademicYear:id,name,start_date,end_date',
                'users:id,name,identifier',
                'students:id,first_name,last_name,current_grade_level,enrollment_status'
            ]);

            // Get school statistics
            $stats = [
                'total_students' => $school->students()->count(),
                'active_students' => $school->students()->where('enrollment_status', 'enrolled')->count(),
                'by_grade_level' => $school->students()
                    ->selectRaw('current_grade_level, COUNT(*) as count')
                    ->groupBy('current_grade_level')
                    ->get(),
                'academic_years' => $school->academicYears()->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'school' => $school,
                    'statistics' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve school',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified school
     */
    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        try {
            $updatedSchool = $this->schoolService->updateSchool($school, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'School updated successfully',
                'data' => $updatedSchool->load(['users:id,name,identifier'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update school',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified school
     */
    public function destroy(School $school): JsonResponse
    {
        try {
            $this->schoolService->deleteSchool($school);

            return response()->json([
                'success' => true,
                'message' => 'School deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete school',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get school dashboard data
     */
    public function getDashboard(School $school): JsonResponse
    {
        try {
            $dashboard = [
                'school_info' => $school->only(['id', 'official_name', 'school_code', 'school_type', 'status']),
                'enrollment_summary' => [
                    'total_students' => $school->students()->count(),
                    'active_students' => $school->students()->where('enrollment_status', 'enrolled')->count(),
                    'new_enrollments' => $school->students()
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count(),
                    'transfers_in' => $school->students()
                        ->where('enrollment_status', 'transferred')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count()
                ],
                'grade_distribution' => $school->students()
                    ->selectRaw('grade_level, COUNT(*) as count')
                    ->groupBy('grade_level')
                    ->orderBy('grade_level')
                    ->get(),
                'recent_activities' => $this->getRecentActivities($school),
                'upcoming_events' => $this->getUpcomingEvents($school)
            ];

            return response()->json([
                'success' => true,
                'data' => $dashboard
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get school dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get school statistics
     */
    public function getStatistics(School $school): JsonResponse
    {
        try {
            $stats = [
                'enrollment' => [
                    'total' => $school->students()->count(),
                    'by_status' => $school->students()
                        ->selectRaw('enrollment_status, COUNT(*) as count')
                        ->groupBy('enrollment_status')
                        ->get(),
                    'by_grade' => $school->students()
                        ->selectRaw('current_grade_level, COUNT(*) as count')
                        ->groupBy('current_grade_level')
                        ->get(),
                    'by_gender' => $school->students()
                        ->selectRaw('gender, COUNT(*) as count')
                        ->groupBy('gender')
                        ->get()
                ],
                'academic' => [
                    'academic_years' => $school->academicYears()->count(),
                    'current_year' => $school->currentAcademicYear?->name,
                    'terms' => $school->academicYears()
                        ->withCount('terms')
                        ->get()
                ],
                'demographics' => [
                    'age_distribution' => $this->getAgeDistribution($school),
                    'geographic_distribution' => $this->getGeographicDistribution($school)
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get school statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students by school with filters
     */
    public function getStudents(School $school, Request $request): JsonResponse
    {
        try {
            $query = $school->students()->with([
                'enrollments',
                'familyRelationships',
                'currentAcademicYear'
            ]);

            // Apply filters
            if ($request->has('status')) {
                $query->where('enrollment_status', $request->status);
            }

            if ($request->has('grade_level')) {
                $query->where('current_grade_level', $request->grade_level);
            }

            if ($request->has('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            }

            $students = $query->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $students
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get school students',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get academic years for a school
     */
    public function getAcademicYears(School $school): JsonResponse
    {
        try {
            $academicYears = $school->academicYears()
                ->with(['terms:id,academic_year_id,name,start_date,end_date'])
                ->orderBy('start_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $academicYears
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get academic years',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set current academic year for a school
     */
    public function setCurrentAcademicYear(Request $request, School $school): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'academic_year_id' => 'required|exists:academic_years,id',
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

            // Update school's current academic year
            $school->update(['current_academic_year_id' => $request->academic_year_id]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Current academic year updated successfully',
                'data' => $school->fresh()->load(['currentAcademicYear:id,name,start_date,end_date'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update current academic year',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get school performance metrics
     */
    public function getPerformanceMetrics(School $school, Request $request): JsonResponse
    {
        try {
            $year = $request->get('year', date('Y'));
            $metrics = $this->schoolService->getSchoolPerformanceMetrics($school, $year);

            return response()->json([
                'success' => true,
                'data' => [
                    'school_id' => $school->id,
                    'year' => $year,
                    'metrics' => $metrics
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // FORM TEMPLATE AND FORM ENGINE INTEGRATION METHODS
    // =====================================================

    /**
     * Get available form templates for school management
     */
    public function getFormTemplates(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $currentTenant = $user->getCurrentTenant();
            if (!$currentTenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have access to any tenant'
                ], 403);
            }

            $query = FormTemplate::where('is_active', true)
                ->where('tenant_id', $currentTenant->id);

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by compliance level
            if ($request->has('compliance_level')) {
                $query->where('compliance_level', $request->compliance_level);
            }

            // Search templates
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }

            $templates = $query->with(['creator:id,name,email'])
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve form templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific form template with configuration
     */
    public function getFormTemplate(FormTemplate $template): JsonResponse
    {
        try {
            $template->load(['creator:id,name,email']);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve form template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new form template for school management
     */
    public function createFormTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|max:100',
            'compliance_level' => 'required|in:basic,standard,strict,comprehensive',
            'form_configuration' => 'required|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'estimated_completion_time' => 'nullable|integer|min:1',
            'tags' => 'nullable|array'
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

            $templateData = array_merge($request->all(), [
                'created_by' => Auth::id(),
                'version' => '1.0',
                'is_active' => true,
                'is_default' => false
            ]);

            $template = FormTemplate::create($templateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form template created successfully',
                'data' => $template->load(['creator:id,name,email'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create form template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing form template
     */
    public function updateFormTemplate(Request $request, FormTemplate $template): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|required|string|max:100',
            'compliance_level' => 'sometimes|required|in:basic,standard,strict,comprehensive',
            'form_configuration' => 'sometimes|required|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'estimated_completion_time' => 'nullable|integer|min:1',
            'tags' => 'nullable|array',
            'is_active' => 'boolean'
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

            $template->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form template updated successfully',
                'data' => $template->fresh()->load(['creator:id,name,email'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update form template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete form template
     */
    public function deleteFormTemplate(FormTemplate $template): JsonResponse
    {
        try {
            // Check if template has active instances
            $activeInstances = $template->formInstances()->where('status', '!=', 'deleted')->count();
            if ($activeInstances > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete template with {$activeInstances} active form instances"
                ], 422);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Form template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete form template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process form submission through Form Engine
     */
    public function processFormSubmission(ProcessFormSubmissionRequest $request, School $school): JsonResponse
    {
        try {
            $result = $this->schoolService->processFormSubmission($school, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Form submitted successfully',
                'data' => [
                    'form_instance' => $result['form_instance'],
                    'workflow_id' => $result['workflow']?->id,
                    'processed_data' => $result['processed_data']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process form submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get form instances for a school
     */
    public function getFormInstances(School $school, Request $request): JsonResponse
    {
        try {
            $query = $school->formInstances()
                ->with(['template:id,name,category', 'creator:id,name,email']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by template category
            if ($request->has('category')) {
                $query->whereHas('template', function($q) use ($request) {
                    $q->where('category', $request->category);
                });
            }

            // Filter by submission date
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('instance_name', 'like', "%{$search}%")
                      ->orWhere('reference_number', 'like', "%{$search}%");
                });
            }

            $instances = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $instances
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve form instances',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific form instance details
     */
    public function getFormInstance(School $school, $instanceId): JsonResponse
    {
        try {
            $instance = $school->formInstances()
                ->with(['template:id,name,category,form_configuration', 'creator:id,name,email'])
                ->findOrFail($instanceId);

            return response()->json([
                'success' => true,
                'data' => $instance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve form instance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update form instance status
     */
    public function updateFormInstanceStatus(Request $request, School $school, $instanceId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,under_review,approved,rejected,completed',
            'notes' => 'nullable|string|max:1000',
            'workflow_state' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $instance = $school->formInstances()->findOrFail($instanceId);

            $instance->update([
                'status' => $request->status,
                'workflow_state' => $request->workflow_state ?? $instance->workflow_state
            ]);

            // Add to workflow history if notes provided
            if ($request->has('notes')) {
                $history = $instance->workflow_history ?? [];
                $history[] = [
                    'action' => 'status_update',
                    'status' => $request->status,
                    'notes' => $request->notes,
                    'updated_by' => Auth::id(),
                    'updated_at' => now()->toISOString()
                ];
                $instance->update(['workflow_history' => $history]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Form instance status updated successfully',
                'data' => $instance->fresh()->load(['template:id,name,category'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update form instance status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get form analytics and insights
     */
    public function getFormAnalytics(School $school, Request $request): JsonResponse
    {
        try {
            $dateRange = $request->get('date_range', '30'); // days
            $startDate = now()->subDays($dateRange);

            $analytics = [
                'submission_summary' => [
                    'total_submissions' => $school->formInstances()->count(),
                    'recent_submissions' => $school->formInstances()
                        ->where('created_at', '>=', $startDate)
                        ->count(),
                    'by_status' => $school->formInstances()
                        ->selectRaw('status, COUNT(*) as count')
                        ->groupBy('status')
                        ->get()
                ],
                'template_usage' => $school->formInstances()
                    ->with('template:id,name,category')
                    ->selectRaw('form_template_id, COUNT(*) as count')
                    ->groupBy('form_template_id')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'completion_rates' => $this->calculateFormCompletionRates($school, $startDate),
                'response_times' => $this->calculateFormResponseTimes($school, $startDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get form analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate form template for school customization
     */
    public function duplicateFormTemplate(Request $request, FormTemplate $template): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'customizations' => 'nullable|array'
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

            // Create new template based on existing one
            $newTemplateData = array_merge($template->toArray(), [
                'id' => null,
                'name' => $request->name,
                'description' => $request->description ?? $template->description,
                'tenant_id' => $request->tenant_id,
                'created_by' => Auth::id(),
                'is_default' => false,
                'version' => '1.0',
                'created_at' => null,
                'updated_at' => null
            ]);

            // Apply customizations if provided
            if ($request->has('customizations')) {
                $newTemplateData = array_merge($newTemplateData, $request->customizations);
            }

            $newTemplate = FormTemplate::create($newTemplateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Form template duplicated successfully',
                'data' => $newTemplate->load(['creator:id,name,email'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate form template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================================
    // PRIVATE HELPER METHODS
    // =====================================================

    /**
     * Calculate form completion rates
     */
    private function calculateFormCompletionRates(School $school, $startDate): array
    {
        $instances = $school->formInstances()
            ->where('created_at', '>=', $startDate)
            ->get();

        $total = $instances->count();
        $completed = $instances->where('status', 'completed')->count();
        $inProgress = $instances->whereIn('status', ['draft', 'submitted', 'under_review'])->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Calculate form response times
     */
    private function calculateFormResponseTimes(School $school, $startDate): array
    {
        $instances = $school->formInstances()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('completed_at')
            ->get();

        if ($instances->isEmpty()) {
            return ['average_response_time' => 0, 'response_times' => []];
        }

        $responseTimes = $instances->map(function($instance) {
            return $instance->created_at->diffInHours($instance->completed_at);
        });

        return [
            'average_response_time' => round($responseTimes->avg(), 2),
            'response_times' => $responseTimes->toArray()
        ];
    }

    // =====================================================
    // ADDITIONAL HELPER METHODS (TO BE IMPLEMENTED)
    // =====================================================

    /**
     * Get recent activities for a school
     */
    private function getRecentActivities(School $school): array
    {
        // TODO: Implement activity logging for schools
        return [];
    }

    /**
     * Get upcoming events for a school
     */
    private function getUpcomingEvents(School $school): array
    {
        // TODO: Implement event management for schools
        return [];
    }

    /**
     * Get age distribution for students
     */
    private function getAgeDistribution(School $school): array
    {
        // TODO: Implement age distribution calculation
        return [];
    }

    /**
     * Get geographic distribution for students
     */
    private function getGeographicDistribution(School $school): array
    {
        // TODO: Implement geographic distribution calculation
        return [];
    }

    /**
     * Get enrollment growth metrics
     */
    private function getEnrollmentGrowth(School $school, $year): array
    {
        // TODO: Implement enrollment growth calculation
        return [];
    }

    /**
     * Get retention rate metrics
     */
    private function getRetentionRate(School $school, $year): array
    {
        // TODO: Implement retention rate calculation
        return [];
    }

    /**
     * Get graduation rate metrics
     */
    private function getGraduationRate(School $school, $year): array
    {
        // TODO: Implement graduation rate calculation
        return [];
    }

    /**
     * Get attendance rate metrics
     */
    private function getAttendanceRate(School $school, $year): array
    {
        // TODO: Implement attendance rate calculation
        return [];
    }

    /**
     * Get academic progress metrics
     */
    private function getAcademicProgress(School $school, $year): array
    {
        // TODO: Implement academic progress calculation
        return [];
    }
}

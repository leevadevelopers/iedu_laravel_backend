<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\SchoolEvent;
use App\Models\Forms\FormTemplate;
use App\Models\Scopes\TenantScope;
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
use Illuminate\Auth\Access\AuthorizationException;

class SchoolController extends Controller
{
    protected $schoolService;

    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }


    /**
     * Display a listing of schools with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', School::class);

            $filters = $request->only(['status', 'school_type', 'state_province', 'country_code', 'search']);
            $perPage = $request->get('per_page', 15);

            // Create cache key based on filters, user, and role (super_admin needs separate cache)
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
            $cacheKey = 'schools_' . md5(serialize($filters) . $perPage . ($user ? $user->id : 'guest') . ($isSuperAdmin ? '_superadmin' : ''));

            // For super_admin, don't use cache or use shorter cache to ensure fresh data
            if ($isSuperAdmin) {
                // Clear any existing cache for super_admin to ensure fresh data
                Cache::forget($cacheKey);
                $schools = $this->schoolService->getSchools($filters, $perPage);
            } else {
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
            }

            return response()->json([
                'success' => true,
                'data' => $schools
            ]);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view schools',
                'error' => $e->getMessage()
            ], 403);
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
            // Check authorization
            $this->authorize('create', School::class);

            // Tenant_id will be set automatically by Tenantable trait from authenticated user
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
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // Load relationships - for super_admin, load without tenant restrictions
            if ($isSuperAdmin) {
                $school->load([
                    'academicYears' => function ($query) {
                        $query->withoutTenantScope();
                    },
                    'currentAcademicYear' => function ($query) {
                        $query->withoutTenantScope();
                    },
                    'users:id,name,identifier',
                    'students' => function ($query) {
                        $query->withoutTenantScope();
                    }
                ]);
            } else {
                $school->load([
                    'academicYears:id,name,start_date,end_date,status',
                    'currentAcademicYear:id,name,start_date,end_date',
                    'users:id,name,identifier',
                    'students:id,first_name,last_name,current_grade_level,enrollment_status'
                ]);
            }

            // Get basic school info
            $data = [
                'school' => $school,
            ];

            // Only include statistics if user has permission
            /** @var \App\Models\User $user */
            $hasStatisticsPermission = $user && $user->hasPermissionTo('schools.statistics', 'api');
            if ($this->isAdministrativeRole($user) || $hasStatisticsPermission) {
                // For super_admin, count without tenant restrictions
                $studentsQuery = $isSuperAdmin
                    ? $school->students()->withoutGlobalScope(TenantScope::class)
                    : $school->students();

                $academicYearsQuery = $isSuperAdmin
                    ? $school->academicYears()->withoutGlobalScope(TenantScope::class)
                    : $school->academicYears();

                $data['statistics'] = [
                    'total_students' => $studentsQuery->count(),
                    'active_students' => $studentsQuery->where('enrollment_status', 'enrolled')->count(),
                    'by_grade_level' => $studentsQuery
                        ->selectRaw('current_grade_level, COUNT(*) as count')
                        ->groupBy('current_grade_level')
                        ->get(),
                    'academic_years' => $academicYearsQuery->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data
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
    public function update(UpdateSchoolRequest $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admin can access any school, others are scoped by tenant
            if ($user && $user->hasRole('super_admin')) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('update', $school);

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
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Super admin can access any school, others are scoped by tenant
            if ($user && $user->hasRole('super_admin')) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('delete', $school);

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
    public function getDashboard($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            /** @var \App\Models\User $user */
            $hasStatisticsPermission = $user && $user->hasPermissionTo('schools.statistics', 'api');
            $canViewStatistics = $this->isAdministrativeRole($user) || $hasStatisticsPermission;

            // Check if user can view statistics
            if (!$canViewStatistics) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view school dashboard statistics'
                ], 403);
            }

            $dashboard = $this->schoolService->getSchoolDashboard($school, true, $isSuperAdmin);

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
     * Get all schools aggregate statistics
     */
    public function getAllSchoolsStatistics(): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewAny', School::class);

            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            $stats = $this->schoolService->getAllSchoolsStatistics($isSuperAdmin);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get schools statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get school statistics
     */
    public function getStatistics($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);
            $this->authorize('viewStatistics', $school);

            $stats = $this->schoolService->getSchoolStatistics($school, true, $isSuperAdmin);

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
    public function getStudents($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // For super_admin, query students without tenant restrictions
            $query = $isSuperAdmin
                ? $school->students()->withoutGlobalScope(TenantScope::class)
                : $school->students();

            $query->with([
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
    public function getAcademicYears($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // For super_admin, query academic years without tenant restrictions
            $query = $isSuperAdmin
                ? $school->academicYears()->withoutGlobalScope(TenantScope::class)
                : $school->academicYears();

            $academicYears = $query
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
    public function setCurrentAcademicYear(Request $request, $id): JsonResponse
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
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('update', $school);

            DB::beginTransaction();

            // Update school's current academic year
            $school->update(['current_academic_year_id' => $request->academic_year_id]);

            DB::commit();

            // Load current academic year - for super_admin, load without tenant restrictions
            $freshSchool = $school->fresh();
            if ($isSuperAdmin) {
                $freshSchool->load(['currentAcademicYear' => function ($query) {
                    $query->withoutTenantScope();
                }]);
            } else {
                $freshSchool->load(['currentAcademicYear:id,name,start_date,end_date']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Current academic year updated successfully',
                'data' => $freshSchool
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
    public function getPerformanceMetrics($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);
            $this->authorize('viewStatistics', $school);

            $year = $request->get('year', date('Y'));
            $metrics = $this->schoolService->getSchoolPerformanceMetrics($school, $year, true, $isSuperAdmin);

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

            $templates = $query->with(['creator:id,name,identifier'])
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
            $template->load(['creator:id,name,identifier']);

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
            'steps' => 'required|array',
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
                'data' => $template->load(['creator:id,name,identifier'])
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
            'steps' => 'sometimes|required|array',
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
                'data' => $template->fresh()->load(['creator:id,name,identifier'])
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
    public function processFormSubmission(ProcessFormSubmissionRequest $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

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
    public function getFormInstances($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // For super_admin, query form instances without tenant restrictions
            $query = $isSuperAdmin
                ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
                : $school->formInstances();

            $query->with(['template:id,name,category', 'creator:id,name,identifier']);

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
    public function getFormInstance($id, $instanceId): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // For super_admin, query form instances without tenant restrictions
            $query = $isSuperAdmin
                ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
                : $school->formInstances();

            $instance = $query
                ->with(['template:id,name,category,form_configuration', 'creator:id,name,identifier'])
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
    public function updateFormInstanceStatus(Request $request, $id, $instanceId): JsonResponse
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
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // For super_admin, query form instances without tenant restrictions
            $query = $isSuperAdmin
                ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
                : $school->formInstances();

            $instance = $query->findOrFail($instanceId);

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
                'data' => $instance->fresh()->load(['template:id,name,category', 'creator:id,name,identifier'])
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
    public function getFormAnalytics($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            $dateRange = $request->get('date_range', '30'); // days
            $startDate = now()->subDays($dateRange);

            // For super_admin, query form instances without tenant restrictions
            $formInstancesQuery = $isSuperAdmin
                ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
                : $school->formInstances();

            $analytics = [
                'submission_summary' => [
                    'total_submissions' => $formInstancesQuery->count(),
                    'recent_submissions' => $formInstancesQuery
                        ->where('created_at', '>=', $startDate)
                        ->count(),
                    'by_status' => $formInstancesQuery
                        ->selectRaw('status, COUNT(*) as count')
                        ->groupBy('status')
                        ->get()
                ],
                'template_usage' => $formInstancesQuery
                    ->with('template:id,name,category')
                    ->selectRaw('form_template_id, COUNT(*) as count')
                    ->groupBy('form_template_id')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),
                'completion_rates' => $this->calculateFormCompletionRates($school, $startDate, $isSuperAdmin),
                'response_times' => $this->calculateFormResponseTimes($school, $startDate, $isSuperAdmin)
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
                'data' => $newTemplate->load(['creator:id,name,identifier'])
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
    private function calculateFormCompletionRates(School $school, $startDate, bool $isSuperAdmin = false): array
    {
        $query = $isSuperAdmin
            ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
            : $school->formInstances();

        $instances = $query
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
    private function calculateFormResponseTimes(School $school, $startDate, bool $isSuperAdmin = false): array
    {
        $query = $isSuperAdmin
            ? $school->formInstances()->withoutGlobalScope(TenantScope::class)
            : $school->formInstances();

        $instances = $query
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
     * Check if user has an administrative role
     */
    private function isAdministrativeRole($user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin', 'tenant_admin', 'owner']);
    }

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
     * Get unified school calendar (academic years + events)
     */
    public function getCalendar($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Super admin can access any school, others are scoped by tenant
            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            // Check authorization
            $this->authorize('view', $school);

            // Get date range from request
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Get academic years that overlap with the date range (or all if no range specified)
            $academicYearsQuery = \App\Models\V1\SIS\School\AcademicYear::where('school_id', $school->id);

            if ($startDate && $endDate) {
                $academicYearsQuery->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                          ->orWhereBetween('end_date', [$startDate, $endDate])
                          ->orWhere(function ($q) use ($startDate, $endDate) {
                              $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                          });
                });
            }

            $academicYears = $academicYearsQuery
                ->with(['terms' => function ($query) {
                    $query->select('id', 'academic_year_id', 'name', 'start_date', 'end_date', 'status')
                          ->orderBy('start_date');
                }])
                ->orderBy('start_date')
                ->get();

            // Build calendar data
            $calendar = [
                'school_id' => $school->id,
                'school_name' => $school->display_name ?? $school->official_name,
                'academic_years' => $academicYears->map(function ($year) {
                    return [
                        'id' => $year->id,
                        'name' => $year->name,
                        'year' => $year->year,
                        'start_date' => $year->start_date,
                        'end_date' => $year->end_date,
                        'status' => $year->status,
                        'is_current' => $year->is_current,
                        'terms' => $year->terms->map(function ($term) {
                            return [
                                'id' => $term->id,
                                'name' => $term->name,
                                'start_date' => $term->start_date,
                                'end_date' => $term->end_date,
                                'status' => $term->status,
                            ];
                        }),
                        'important_dates' => [
                            'enrollment_start' => $year->enrollment_start_date,
                            'enrollment_end' => $year->enrollment_end_date,
                            'registration_deadline' => $year->registration_deadline,
                        ],
                        'holidays' => $year->holidays_json ? (is_array($year->holidays_json) ? $year->holidays_json : json_decode($year->holidays_json, true)) : [],
                    ];
                }),
                'events' => $this->getSchoolEvents($school, $startDate, $endDate),
            ];

            // Filter events by date range if provided
            if ($startDate && $endDate) {
                // Filter holidays within date range
                $calendar['academic_years'] = $calendar['academic_years']->map(function ($year) use ($startDate, $endDate) {
                    if (isset($year['holidays']) && is_array($year['holidays'])) {
                        $year['holidays'] = array_filter($year['holidays'], function ($holiday) use ($startDate, $endDate) {
                            $holidayDate = $holiday['date'] ?? null;
                            if (!$holidayDate) return false;
                            return $holidayDate >= $startDate && $holidayDate <= $endDate;
                        });
                    }
                    return $year;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $calendar
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get school calendar',
                'error' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Get school events
     */
    public function getEvents($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            $this->authorize('view', $school);

            $query = SchoolEvent::where('school_id', $school->id);

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('end_date', '>=', $request->get('start_date'));
            }
            if ($request->has('end_date')) {
                $query->where('start_date', '<=', $request->get('end_date'));
            }

            // Filter by event type
            if ($request->has('event_type')) {
                $query->where('event_type', $request->get('event_type'));
            }

            $events = $query->orderBy('start_date')->get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get school events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a school event
     */
    public function createEvent($id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            $this->authorize('update', $school);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'description' => 'nullable|string|max:1000',
                'event_type' => 'required|in:academic,holiday,activity,meeting,exam,other',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'all_day' => 'boolean',
                'location' => 'nullable|string|max:200',
                'color' => 'nullable|string|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = SchoolEvent::create([
                'school_id' => $school->id,
                'title' => $request->get('title'),
                'description' => $request->get('description'),
                'event_type' => $request->get('event_type'),
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'all_day' => $request->get('all_day', false),
                'location' => $request->get('location'),
                'color' => $request->get('color', '#3b82f6'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a school event
     */
    public function updateEvent($id, $eventId, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            $this->authorize('update', $school);

            $event = SchoolEvent::where('school_id', $school->id)->findOrFail($eventId);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:200',
                'description' => 'nullable|string|max:1000',
                'event_type' => 'sometimes|required|in:academic,holiday,activity,meeting,exam,other',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'all_day' => 'boolean',
                'location' => 'nullable|string|max:200',
                'color' => 'nullable|string|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $event->update($request->only([
                'title', 'description', 'event_type', 'start_date', 'end_date',
                'all_day', 'location', 'color'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a school event
     */
    public function deleteEvent($id, $eventId): JsonResponse
    {
        try {
            $user = Auth::user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            if ($isSuperAdmin) {
                $school = School::withoutTenantScope()->findOrFail($id);
            } else {
                $school = School::findOrFail($id);
            }

            $this->authorize('update', $school);

            $event = SchoolEvent::where('school_id', $school->id)->findOrFail($eventId);
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get school events for calendar
     */
    private function getSchoolEvents(School $school, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = SchoolEvent::where('school_id', $school->id);

        if ($startDate) {
            $query->where('end_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('start_date', '<=', $endDate);
        }

        return $query->orderBy('start_date')->get()->map(function ($event) {
            return [
                'id' => $event->id,
                'school_id' => $event->school_id,
                'title' => $event->title,
                'description' => $event->description,
                'event_type' => $event->event_type,
                'start_date' => $event->all_day ? $event->start_date->toDateString() : $event->start_date->toDateTimeString(),
                'end_date' => $event->all_day ? $event->end_date->toDateString() : $event->end_date->toDateTimeString(),
                'all_day' => $event->all_day,
                'location' => $event->location,
                'color' => $event->color,
                'created_at' => $event->created_at->toDateTimeString(),
                'updated_at' => $event->updated_at->toDateTimeString(),
            ];
        })->toArray();
    }
}

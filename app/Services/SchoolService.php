<?php

namespace App\Services;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\Student\Student;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SchoolService
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Get schools with filters and pagination
     *
     * @param array $filters
     * @param int $perPage
     * @param \App\Models\User|null $user - If provided and user is not super_admin, filters by tenant
     * @return LengthAwarePaginator
     */
    public function getSchools(array $filters, int $perPage = 15, $user = null): LengthAwarePaginator
    {
        // Check if user is super_admin
        $isSuperAdmin = false;
        if ($user) {
            $isSuperAdmin = $user->hasRole('super_admin');
        }

        // Build query with or without tenant scope
        $query = $isSuperAdmin
            ? School::withoutTenantScope()->select([
                'id', 'tenant_id', 'school_code', 'official_name', 'display_name',
                'school_type', 'status', 'city', 'state_province', 'country_code',
                'email', 'phone', 'website', 'current_enrollment', 'staff_count',
                'created_at', 'updated_at'
            ])
            : School::select([
                'id', 'tenant_id', 'school_code', 'official_name', 'display_name',
                'school_type', 'status', 'city', 'state_province', 'country_code',
                'email', 'phone', 'website', 'current_enrollment', 'staff_count',
                'created_at', 'updated_at'
            ]);

        $query->with([
            'academicYears:id,school_id,name,start_date,end_date',
            'currentAcademicYear:id,school_id,name',
            'users:id,school_id,name,identifier'
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['school_type'])) {
            $query->where('school_type', $filters['school_type']);
        }

        if (isset($filters['state_province'])) {
            $query->where('state_province', $filters['state_province']);
        }

        if (isset($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('official_name', 'like', "%{$search}%")
                    ->orWhere('school_code', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('official_name')->paginate($perPage);
    }

    /**
     * Create a new school
     */
    public function createSchool(array $data): array
    {
        try {
            DB::beginTransaction();

            // Prepare school data
            $schoolData = array_merge($data, [
                'status' => 'setup',
                'current_enrollment' => 0,
                'staff_count' => 0,
                'feature_flags' => $data['feature_flags'] ?? [],
                'integration_settings' => $data['integration_settings'] ?? [],
                'branding_configuration' => $data['branding_configuration'] ?? []
            ]);

            $school = School::create($schoolData);

            // Create school_users association if created_by is provided
            if (isset($data['created_by'])) {
                DB::table('school_users')->insert([
                    'school_id' => $school->id,
                    'user_id' => $data['created_by'],
                    'role' => 'admin', // Default role for school creator
                    'status' => 'active',
                    'start_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Process form data through Form Engine if provided
            $formInstance = null;
            if (isset($data['form_data'])) {
                try {
                    $processedData = $this->formEngineService->processFormData('school_registration', $data['form_data']);
                    $formInstance = $this->formEngineService->createFormInstance('school_registration', $processedData, 'School', $school->id);
                } catch (\Exception $e) {
                    Log::warning('Form Engine processing failed for school creation: ' . $e->getMessage());
                }
            }

            // Start school setup workflow
            $workflow = null;
            try {
                $workflow = $this->workflowService->startWorkflow($school, 'school_setup', [
                    'steps' => [
                        [
                            'step_number' => 1,
                            'step_name' => 'Initial Setup',
                            'step_type' => 'setup',
                            'required_role' => 'school_admin',
                            'instructions' => 'Complete initial school configuration and setup'
                        ],
                        [
                            'step_number' => 2,
                            'step_name' => 'Staff Assignment',
                            'step_type' => 'assignment',
                            'required_role' => 'school_admin',
                            'instructions' => 'Assign staff members and define roles'
                        ],
                        [
                            'step_number' => 3,
                            'step_name' => 'Curriculum Setup',
                            'step_type' => 'setup',
                            'required_role' => 'curriculum_coordinator',
                            'instructions' => 'Configure curriculum and academic programs'
                        ],
                        [
                            'step_number' => 4,
                            'step_name' => 'Final Approval',
                            'step_type' => 'approval',
                            'required_role' => 'principal',
                            'instructions' => 'Final review and approval of school setup'
                        ]
                    ]
                ]);
            } catch (\Exception $e) {
                Log::warning('Workflow start failed for school creation', [
                    'school_id' => $school->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't throw the exception, just log it and continue
            }

            DB::commit();

            // Clear schools cache after creation
            $this->clearSchoolsCache();

            return [
                'school' => $school,
                'form_instance' => $formInstance,
                'workflow' => $workflow
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing school
     */
    public function updateSchool(School $school, array $data): School
    {
        try {
            DB::beginTransaction();

            $school->update($data);

            DB::commit();

            // Clear schools cache after update
            $this->clearSchoolsCache();

            return $school->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a school
     */
    public function deleteSchool(School $school): bool
    {
        try {
            DB::beginTransaction();

            // Check if school has active students
            $activeStudents = $school->students()->where('enrollment_status', 'enrolled')->count();
            if ($activeStudents > 0) {
                throw new \Exception("Cannot delete school with {$activeStudents} active students");
            }

            // Soft delete school
            $school->update(['status' => 'archived']);
            $school->delete();

            DB::commit();

            // Clear schools cache after deletion
            $this->clearSchoolsCache();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Clear schools cache
     */
    private function clearSchoolsCache(): void
    {
        // Clear all schools-related cache
        $keys = Cache::get('schools_cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('schools_cache_keys');
    }

    /**
     * Get school dashboard data
     */
    public function getSchoolDashboard(School $school): array
    {
        return [
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
                ->selectRaw('current_grade_level, COUNT(*) as count')
                ->groupBy('current_grade_level')
                ->orderBy('current_grade_level')
                ->get(),
            'recent_activities' => $this->getRecentActivities($school),
            'upcoming_events' => $this->getUpcomingEvents($school)
        ];
    }

    /**
     * Get school statistics
     */
    public function getSchoolStatistics(School $school): array
    {
        return [
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
    }

    /**
     * Get students by school with filters
     */
    public function getSchoolStudents(School $school, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $school->students()->with([
            'enrollments',
            'familyRelationships',
            'currentAcademicYear'
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('enrollment_status', $filters['status']);
        }

        if (isset($filters['grade_level'])) {
            $query->where('current_grade_level', $filters['grade_level']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    /**
     * Get academic years for a school
     */
    public function getSchoolAcademicYears(School $school): Collection
    {
        return $school->academicYears()
            ->with(['terms:id,academic_year_id,name,start_date,end_date'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Set current academic year for a school
     */
    public function setCurrentAcademicYear(School $school, int $academicYearId): School
    {
        try {
            DB::beginTransaction();

            $school->update(['current_academic_year_id' => $academicYearId]);

            DB::commit();

            return $school->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get school performance metrics
     */
    public function getSchoolPerformanceMetrics(School $school, int $year): array
    {
        return [
            'enrollment_growth' => $this->getEnrollmentGrowth($school, $year),
            'retention_rate' => $this->getRetentionRate($school, $year),
            'graduation_rate' => $this->getGraduationRate($school, $year),
            'attendance_rate' => $this->getAttendanceRate($school, $year),
            'academic_progress' => $this->getAcademicProgress($school, $year)
        ];
    }

    /**
     * Get form templates for school management
     */
    public function getFormTemplates(int $tenantId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = FormTemplate::where('is_active', true)
            ->where('tenant_id', $tenantId);

        // Filter by category
        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filter by compliance level
        if (isset($filters['compliance_level'])) {
            $query->where('compliance_level', $filters['compliance_level']);
        }

        // Search templates
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return $query->with(['creator:id,name,email'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a new form template
     */
    public function createFormTemplate(array $data, int $createdBy): FormTemplate
    {
        try {
            DB::beginTransaction();

            $templateData = array_merge($data, [
                'created_by' => $createdBy,
                'version' => '1.0',
                'is_active' => true,
                'is_default' => false
            ]);

            $template = FormTemplate::create($templateData);

            DB::commit();

            return $template;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update existing form template
     */
    public function updateFormTemplate(FormTemplate $template, array $data): FormTemplate
    {
        try {
            DB::beginTransaction();

            $template->update($data);

            DB::commit();

            return $template->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete form template
     */
    public function deleteFormTemplate(FormTemplate $template): bool
    {
        // Check if template has active instances
        $activeInstances = $template->formInstances()->where('status', '!=', 'deleted')->count();
        if ($activeInstances > 0) {
            throw new \Exception("Cannot delete template with {$activeInstances} active form instances");
        }

        return $template->delete();
    }

    /**
     * Process form submission through Form Engine
     */
    public function processFormSubmission(School $school, array $data): array
    {
        try {
            DB::beginTransaction();

            // Get form template
            $template = FormTemplate::findOrFail($data['form_template_id']);

            // Process form data through Form Engine
            $processedData = $this->formEngineService->processFormData(
                $template->category,
                $data['form_data']
            );

            // Create form instance
            $formInstance = $this->formEngineService->createFormInstance(
                $template->category,
                $processedData,
                'School',
                $school->id
            );

            // Start workflow if configured
            $workflow = null;
            if ($template->workflow_enabled && $template->workflow_configuration) {
                $workflow = $this->workflowService->startWorkflow($formInstance, 'form_approval', [
                    'steps' => $template->workflow_configuration['steps'] ?? ['review', 'approval'],
                    'form_instance_id' => $formInstance->id
                ]);
            }

            DB::commit();

            return [
                'form_instance' => $formInstance,
                'workflow' => $workflow,
                'processed_data' => $processedData
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get form instances for a school
     */
    public function getFormInstances(School $school, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $school->formInstances()
            ->with(['template:id,name,category', 'creator:id,name,email']);

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by template category
        if (isset($filters['category'])) {
            $query->whereHas('template', function($q) use ($filters) {
                $q->where('category', $filters['category']);
            });
        }

        // Filter by submission date
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Search
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('instance_name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Update form instance status
     */
    public function updateFormInstanceStatus($instanceId, array $data, int $updatedBy): array
    {
        $instance = School::findOrFail($instanceId)->formInstances()->findOrFail($instanceId);

        $instance->update([
            'status' => $data['status'],
            'workflow_state' => $data['workflow_state'] ?? $instance->workflow_state
        ]);

        // Add to workflow history if notes provided
        if (isset($data['notes'])) {
            $history = $instance->workflow_history ?? [];
            $history[] = [
                'action' => 'status_update',
                'status' => $data['status'],
                'notes' => $data['notes'],
                'updated_by' => $updatedBy,
                'updated_at' => now()->toISOString()
            ];
            $instance->update(['workflow_history' => $history]);
        }

        return $instance->toArray();
    }

    /**
     * Get form analytics and insights
     */
    public function getFormAnalytics(School $school, int $dateRange = 30): array
    {
        $startDate = now()->subDays($dateRange);

        return [
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
    }

    /**
     * Duplicate form template for school customization
     */
    public function duplicateFormTemplate(FormTemplate $template, array $data, int $createdBy): FormTemplate
    {
        try {
            DB::beginTransaction();

            // Create new template based on existing one
            $newTemplateData = array_merge($template->toArray(), [
                'id' => null,
                'name' => $data['name'],
                'description' => $data['description'] ?? $template->description,
                'tenant_id' => $data['tenant_id'],
                'created_by' => $createdBy,
                'is_default' => false,
                'version' => '1.0',
                'created_at' => null,
                'updated_at' => null
            ]);

            // Apply customizations if provided
            if (isset($data['customizations'])) {
                $newTemplateData = array_merge($newTemplateData, $data['customizations']);
            }

            $newTemplate = FormTemplate::create($newTemplateData);

            DB::commit();

            return $newTemplate;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
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

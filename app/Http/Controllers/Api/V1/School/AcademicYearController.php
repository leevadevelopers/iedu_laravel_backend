<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
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

            // Apply filters
            if ($request->has('school_id')) {
                $query->where('school_id', $request->school_id);
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

        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'year' => 'required|string|max:10',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
            'status' => 'required|in:planning,active,completed,archived',
            'enrollment_start_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_end_date' => 'nullable|date|before:end_date',
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

            // Check for overlapping academic years
            $overlapping = AcademicYear::where('school_id', $request->school_id)
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

            // Create academic year
            $academicYearData = $request->except(['form_data', 'holidays']);
            $academicYearData['created_by'] = $user->id;
            $academicYearData['tenant_id'] = $request->tenant_id;
            $academicYearData['holidays_json'] = $request->holidays ? json_encode($request->holidays) : null;

            $academicYear = AcademicYear::create($academicYearData);

            // If this is set as current, update other academic years
            if ($request->is_current) {
                AcademicYear::where('school_id', $request->school_id)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('academic_year_setup', $request->form_data);
                $this->formEngineService->createFormInstance('academic_year_setup', $processedData, 'AcademicYear', $academicYear->id, $request->tenant_id);
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
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'year' => 'sometimes|required|string|max:10',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
            'status' => 'sometimes|required|in:planning,active,completed,archived',
            'enrollment_start_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_end_date' => 'nullable|date|before:end_date',
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
            DB::beginTransaction();

            // Check if academic year has active students
            $activeStudents = $academicYear->students()->where('status', 'active')->count();
            if ($activeStudents > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete academic year with {$activeStudents} active students"
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
    public function getCurrent(int $schoolId): JsonResponse
    {
        try {
            $currentYear = AcademicYear::where('school_id', $schoolId)
                ->where('is_current', true)
                ->with(['terms:id,academic_year_id,name,start_date,end_date,status'])
                ->first();

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

        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'tenant_id' => 'required|exists:tenants,id',
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
                // Check for overlapping dates
                $overlapping = AcademicYear::where('school_id', $request->school_id)
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
                    $academicYear = AcademicYear::create([
                        'school_id' => $request->school_id,
                        'name' => $yearData['name'],
                        'year' => $yearData['year'],
                        'start_date' => $yearData['start_date'],
                        'end_date' => $yearData['end_date'],
                        'description' => $yearData['description'] ?? null,
                        'status' => 'planning',
                        'is_current' => false,
                        'created_by' => $user->id,
                        'tenant_id' => $request->tenant_id
                    ]);

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
            $stats = [
                'total_academic_years' => AcademicYear::count(),
                'by_status' => AcademicYear::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_school' => AcademicYear::selectRaw('school_id, COUNT(*) as count')
                    ->with('school:id,official_name')
                    ->groupBy('school_id')
                    ->get(),
                'current_years' => AcademicYear::where('is_current', true)->count(),
                'active_years' => AcademicYear::where('status', 'active')->count(),
                'recent_years' => AcademicYear::where('created_at', '>=', now()->subDays(30))
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

            // Apply additional filters
            if ($request->has('school_id')) {
                $query->where('school_id', $request->school_id);
            }

            if ($request->has('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }

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
}

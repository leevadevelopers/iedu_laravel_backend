<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Services\V1\Academic\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get academic overview analytics
     */
    public function academicOverview(Request $request): JsonResponse
    {
        try {
            $overview = $this->analyticsService->getAcademicOverview($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $overview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch academic overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get grade distribution analytics
     */
    public function gradeDistribution(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
            'term' => 'nullable|string|in:first_term,second_term,third_term,annual'
        ]);

        try {
            $distribution = $this->analyticsService->getGradeDistribution($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $distribution
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch grade distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subject performance analytics
     */
    public function subjectPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade_level' => 'nullable|string',
            'term' => 'nullable|string|in:first_term,second_term,third_term,annual'
        ]);

        try {
            $performance = $this->analyticsService->getSubjectPerformance($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $performance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subject performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher statistics
     */
    public function teacherStats(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => 'nullable|exists:teachers,id',
            'department' => 'nullable|string',
            'academic_year_id' => 'nullable|exists:academic_years,id'
        ]);

        try {
            $stats = $this->analyticsService->getTeacherStats($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch teacher statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get class statistics
     */
    public function classStats(int $classId, Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'term' => 'nullable|string|in:first_term,second_term,third_term,annual'
        ]);

        try {
            $stats = $this->analyticsService->getClassStats($classId, $request->all());

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch class statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get student performance trends
     */
    public function studentPerformanceTrends(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'subject_id' => 'nullable|exists:subjects,id'
        ]);

        try {
            $trends = $this->analyticsService->getStudentPerformanceTrends($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch student performance trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance analytics
     */
    public function attendanceAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'class_id' => 'nullable|exists:classes,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        try {
            $analytics = $this->analyticsService->getAttendanceAnalytics($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch attendance analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comparative analytics
     */
    public function comparativeAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'comparison_type' => 'required|in:classes,subjects,teachers,academic_years',
            'entity_ids' => 'required|array|min:2|max:5',
            'entity_ids.*' => 'integer',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'term' => 'nullable|string|in:first_term,second_term,third_term,annual'
        ]);

        try {
            $comparison = $this->analyticsService->getComparativeAnalytics($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $comparison
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch comparative analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function exportAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|in:academic_overview,grade_distribution,subject_performance,teacher_stats,class_stats',
            'format' => 'required|in:pdf,excel,csv',
            'filters' => 'nullable|array'
        ]);

        try {
            $exportData = $this->analyticsService->exportAnalytics($request->all());

            return response()->json([
                'status' => 'success',
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to export analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

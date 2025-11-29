<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Services\V1\Academic\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
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

}

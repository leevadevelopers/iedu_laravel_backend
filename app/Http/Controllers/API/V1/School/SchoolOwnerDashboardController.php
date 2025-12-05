<?php

namespace App\Http\Controllers\API\V1\School;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\SchoolOwnerDashboardService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolOwnerDashboardController extends BaseController
{
    public function __construct(
        private SchoolOwnerDashboardService $dashboardService,
        private SchoolContextService $schoolContextService
    ) {}

    /**
     * Get school owner dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            // Get current school ID from context
            $schoolId = $this->schoolContextService->getCurrentSchoolId();
            
            if (!$schoolId) {
                return $this->errorResponse(
                    'No school context available. Please select a school.',
                    403
                );
            }
            
            $data = [
                'statistics' => $this->dashboardService->getStatistics($schoolId),
                'alerts' => $this->dashboardService->getAlerts($schoolId),
                'revenue' => $this->dashboardService->getRevenue($schoolId, 'trimester'),
                'recent_activity' => $this->dashboardService->getRecentActivity($schoolId, 10),
                'attendance_stats' => $this->dashboardService->getAttendanceStats($schoolId, 'week'),
            ];
            
            return $this->successResponse($data, 'Dashboard data retrieved successfully');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to load dashboard data: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->schoolContextService->getCurrentSchoolId();
            
            if (!$schoolId) {
                return $this->errorResponse(
                    'No school context available. Please select a school.',
                    403
                );
            }
            
            $this->dashboardService->clearCache($schoolId);
            
            return $this->successResponse(
                null,
                'Dashboard cache cleared successfully'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to clear cache: ' . $e->getMessage(),
                500
            );
        }
    }
}


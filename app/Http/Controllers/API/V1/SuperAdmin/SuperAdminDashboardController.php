<?php

namespace App\Http\Controllers\API\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        private SuperAdminDashboardService $dashboardService
    ) {}

    /**
     * Get dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $data = [
                'statistics' => $this->dashboardService->getStatistics(),
                'recent_activity' => $this->dashboardService->getRecentActivity(),
                'growth_chart' => $this->dashboardService->getGrowthChart(),
                'alerts' => $this->dashboardService->getAlerts(),
                'recent_schools' => $this->dashboardService->getRecentSchools(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(): JsonResponse
    {
        $this->dashboardService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully',
        ]);
    }
}


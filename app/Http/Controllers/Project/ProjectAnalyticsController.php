<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectAnalyticsController extends Controller
{
    public function __construct(
        private ProjectAnalyticsService $analyticsService
    ) {}

    public function getProjectDashboard(int $projectId): JsonResponse
    {
        $dashboard = $this->analyticsService->getProjectDashboard($projectId);
        
        return response()->json([
            'data' => $dashboard
        ]);
    }

    public function getProjectHealth(int $projectId): JsonResponse
    {
        $health = $this->analyticsService->calculateProjectHealth($projectId);
        
        return response()->json([
            'data' => $health
        ]);
    }

    public function getProjectProgress(int $projectId): JsonResponse
    {
        $progress = $this->analyticsService->calculateProgress($projectId);
        
        return response()->json([
            'data' => $progress
        ]);
    }

    public function getRiskAnalysis(int $projectId): JsonResponse
    {
        $risks = $this->analyticsService->analyzeRisks($projectId);
        
        return response()->json([
            'data' => $risks
        ]);
    }

    public function getBudgetAnalysis(int $projectId): JsonResponse
    {
        $budget = $this->analyticsService->analyzeBudget($projectId);
        
        return response()->json([
            'data' => $budget
        ]);
    }
}

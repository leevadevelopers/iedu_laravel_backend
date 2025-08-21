<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectWorkflowController extends Controller
{
    public function __construct(
        private ProjectWorkflowService $workflowService
    ) {}

    public function getWorkflowStatus(int $projectId): JsonResponse
    {
        $status = $this->workflowService->getWorkflowStatus($projectId);
        
        return response()->json([
            'data' => $status
        ]);
    }

    public function transitionToNext(int $projectId): JsonResponse
    {
        $result = $this->workflowService->transitionToNext($projectId);
        
        return response()->json([
            'data' => $result,
            'message' => 'Project workflow transitioned successfully'
        ]);
    }

    public function getAvailableTransitions(int $projectId): JsonResponse
    {
        $transitions = $this->workflowService->getAvailableTransitions($projectId);
        
        return response()->json([
            'data' => $transitions
        ]);
    }
}

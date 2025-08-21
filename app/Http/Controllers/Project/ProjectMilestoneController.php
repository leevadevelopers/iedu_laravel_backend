<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectMilestoneService;
use App\Http\Requests\Project\CreateMilestoneRequest;
use App\Http\Requests\Project\UpdateMilestoneRequest;
use Illuminate\Http\JsonResponse;

class ProjectMilestoneController extends Controller
{
    public function __construct(
        private ProjectMilestoneService $milestoneService
    ) {}

    public function index(int $projectId): JsonResponse
    {
        $milestones = $this->milestoneService->getProjectMilestones($projectId);
        
        return response()->json([
            'data' => $milestones
        ]);
    }

    public function store(CreateMilestoneRequest $request, int $projectId): JsonResponse
    {
        $milestone = $this->milestoneService->create($projectId, $request->validated());
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone created successfully'
        ], 201);
    }

    public function update(UpdateMilestoneRequest $request, int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = $this->milestoneService->update($milestoneId, $request->validated());
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone updated successfully'
        ]);
    }

    public function complete(int $projectId, int $milestoneId): JsonResponse
    {
        $milestone = $this->milestoneService->complete($milestoneId);
        
        return response()->json([
            'data' => $milestone,
            'message' => 'Milestone marked as completed'
        ]);
    }
}

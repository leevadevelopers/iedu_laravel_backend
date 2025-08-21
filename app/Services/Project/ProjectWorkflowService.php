<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Services\Shared\WorkflowEngineService;
use App\Models\Project\Project;

class ProjectWorkflowService
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private WorkflowEngineService $workflowEngine
    ) {}

    public function getWorkflowStatus(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        return [
            'current_status' => $project->status,
            'workflow_state' => $project->workflow_state,
            'available_transitions' => $this->getAvailableTransitions($projectId),
            'approval_chain' => $this->getApprovalChain($project),
            'history' => $this->getWorkflowHistory($project)
        ];
    }

    public function transitionToNext(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        $result = $this->workflowEngine->executeTransition($project);
        
        return $result;
    }

    public function getAvailableTransitions(int $projectId): array
    {
        $project = $this->projectRepository->find($projectId);
        
        return $this->workflowEngine->getAvailableTransitions($project);
    }

    private function getApprovalChain(Project $project): array
    {
        // Return approval chain based on project methodology and value
        return $this->workflowEngine->getApprovalChain($project);
    }

    private function getWorkflowHistory(Project $project): array
    {
        // Return workflow transition history
        return $this->workflowEngine->getWorkflowHistory($project);
    }
}

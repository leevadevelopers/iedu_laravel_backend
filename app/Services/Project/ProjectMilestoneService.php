<?php

namespace App\Services\Project;

use App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface;
use App\Models\Project\ProjectMilestone;
use App\Events\Project\MilestoneCompleted;

class ProjectMilestoneService
{
    public function __construct(
        private ProjectMilestoneRepositoryInterface $milestoneRepository
    ) {}

    public function getProjectMilestones(int $projectId): array
    {
        return $this->milestoneRepository->getByProject($projectId);
    }

    public function create(int $projectId, array $data): ProjectMilestone
    {
        $data['project_id'] = $projectId;
        $milestone = $this->milestoneRepository->create($data);
        
        return $milestone;
    }

    public function update(int $milestoneId, array $data): ProjectMilestone
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        return $this->milestoneRepository->update($milestone, $data);
    }

    public function complete(int $milestoneId): ProjectMilestone
    {
        $milestone = $this->milestoneRepository->find($milestoneId);
        
        $milestone = $this->milestoneRepository->update($milestone, [
            'status' => 'completed',
            'completion_date' => now()
        ]);
        
        event(new MilestoneCompleted($milestone));
        
        return $milestone;
    }
}

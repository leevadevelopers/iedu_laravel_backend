<?php

namespace App\Repositories\Project;

use App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface;
use App\Models\Project\ProjectMilestone;

class ProjectMilestoneRepository implements ProjectMilestoneRepositoryInterface
{
    public function find(int $id): ?ProjectMilestone
    {
        return ProjectMilestone::find($id);
    }

    public function create(array $data): ProjectMilestone
    {
        return ProjectMilestone::create($data);
    }

    public function update(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        $milestone->update($data);
        return $milestone->fresh();
    }

    public function delete(ProjectMilestone $milestone): bool
    {
        return $milestone->delete();
    }

    public function getByProject(int $projectId): array
    {
        return ProjectMilestone::where('project_id', $projectId)
                              ->orderBy('target_date')
                              ->get()
                              ->toArray();
    }

    public function getUpcomingMilestones(int $days = 30): array
    {
        return ProjectMilestone::whereBetween('target_date', [now(), now()->addDays($days)])
                              ->where('status', '!=', 'completed')
                              ->with('project')
                              ->get()
                              ->toArray();
    }

    public function getOverdueMilestones(): array
    {
        return ProjectMilestone::where('target_date', '<', now())
                              ->where('status', '!=', 'completed')
                              ->with('project')
                              ->get()
                              ->toArray();
    }
}

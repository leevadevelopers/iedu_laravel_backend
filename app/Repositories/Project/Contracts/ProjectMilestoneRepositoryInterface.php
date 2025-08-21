<?php

namespace App\Repositories\Project\Contracts;

use App\Models\Project\ProjectMilestone;

interface ProjectMilestoneRepositoryInterface
{
    public function find(int $id): ?ProjectMilestone;
    
    public function create(array $data): ProjectMilestone;
    
    public function update(ProjectMilestone $milestone, array $data): ProjectMilestone;
    
    public function delete(ProjectMilestone $milestone): bool;
    
    public function getByProject(int $projectId): array;
    
    public function getUpcomingMilestones(int $days = 30): array;
    
    public function getOverdueMilestones(): array;
}

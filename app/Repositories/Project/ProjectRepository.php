<?php

namespace App\Repositories\Project;

use App\Repositories\Project\Contracts\ProjectRepositoryInterface;
use App\Models\Project\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function find(int $id): ?Project
    {
        return Project::with(['creator', 'milestones', 'formInstance'])->find($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh();
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query();

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('code', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['methodology_type'])) {
            $query->where('methodology_type', $filters['methodology_type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['budget_min'])) {
            $query->where('budget', '>=', $filters['budget_min']);
        }

        if (isset($filters['budget_max'])) {
            $query->where('budget', '<=', $filters['budget_max']);
        }

        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        return $query->with(['creator', 'milestones'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    public function updateStatus(Project $project, string $status): Project
    {
        $project->update(['status' => $status]);
        return $project->fresh();
    }

    public function getNextSequenceNumber(): int
    {
        $lastProject = Project::orderBy('id', 'desc')->first();
        return $lastProject ? $lastProject->id + 1 : 1;
    }

    public function getByMethodology(string $methodology): array
    {
        return Project::where('methodology_type', $methodology)
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }

    public function getActiveProjects(): array
    {
        return Project::where('status', 'active')
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }

    public function getProjectsByDateRange($startDate, $endDate): array
    {
        return Project::whereBetween('start_date', [$startDate, $endDate])
                     ->orWhereBetween('end_date', [$startDate, $endDate])
                     ->with(['creator', 'milestones'])
                     ->get()
                     ->toArray();
    }
}

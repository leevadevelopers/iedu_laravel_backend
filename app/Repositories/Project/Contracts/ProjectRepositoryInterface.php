<?php

namespace App\Repositories\Project\Contracts;

use App\Models\Project\Project;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function find(int $id): ?Project;

    public function create(array $data): Project;

    public function update(Project $project, array $data): Project;

    public function delete(Project $project): bool;

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function updateStatus(Project $project, string $status): Project;

    public function getNextSequenceNumber(): int;

    public function getActiveProjects(): array;

    public function getProjectsByDateRange($startDate, $endDate): array;
}

<?php

namespace App\Repositories\V1\Academic;

use App\Services\SchoolContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseAcademicRepository
{
    protected Model $model;
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
        $this->model = app($this->getModelClass());
    }

    /**
     * Get the model class name
     */
    abstract protected function getModelClass(): string;

    /**
     * Get current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return $this->schoolContextService->getCurrentSchoolId();
    }

    /**
     * Apply school scope to query
     */
    protected function schoolScoped(): Builder
    {
        return $this->model->where('school_id', $this->getCurrentSchoolId());
    }

    /**
     * Find by ID with school scope
     */
    public function find(int $id): ?Model
    {
        return $this->schoolScoped()->find($id);
    }

    /**
     * Find by ID or fail with school scope
     */
    public function findOrFail(int $id): Model
    {
        return $this->schoolScoped()->findOrFail($id);
    }

    /**
     * Get all records with school scope
     */
    public function all(): Collection
    {
        return $this->schoolScoped()->get();
    }

    /**
     * Create new record
     */
    public function create(array $data): Model
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        return $this->model->create($data);
    }

    /**
     * Update existing record
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    /**
     * Delete record
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Get count with school scope
     */
    public function count(): int
    {
        return $this->schoolScoped()->count();
    }

    /**
     * Apply common filters to query
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['search'])) {
            $query = $this->applySearch($query, $filters['search']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $direction);
        }

        return $query;
    }

    /**
     * Apply search filter (override in child classes)
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

    /**
     * Get paginated results with filters
     */
    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = $this->schoolScoped();
        $query = $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}

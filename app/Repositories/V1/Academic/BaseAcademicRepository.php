<?php

namespace App\Repositories\V1\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

abstract class BaseAcademicRepository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    /**
     * Get the model class name
     */
    abstract protected function getModelClass(): string;

    /**
     * Get current school ID from user's school_users relationship
     */
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $school = $user->activeSchools()->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools');
        }

        return $school->id;
    }

    /**
     * Get base query (global scope handles tenant filtering automatically)
     */
    protected function baseQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Find by ID (tenant scope applied automatically)
     */
    public function find(int $id): ?Model
    {
        return $this->baseQuery()->find($id);
    }

    /**
     * Find by ID or fail (tenant scope applied automatically)
     */
    public function findOrFail(int $id): Model
    {
        return $this->baseQuery()->findOrFail($id);
    }

    /**
     * Get all records (tenant scope applied automatically)
     */
    public function all(): Collection
    {
        return $this->baseQuery()->get();
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
     * Get count (tenant scope applied automatically)
     */
    public function count(): int
    {
        return $this->baseQuery()->count();
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
     * Get paginated results with filters (tenant scope applied automatically)
     */
    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = $this->baseQuery();
        $query = $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}

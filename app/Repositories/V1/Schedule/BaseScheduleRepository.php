<?php

namespace App\Repositories\V1\Schedule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

abstract class BaseScheduleRepository
{
    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    abstract protected function getModelClass(): string;

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

    public function schoolScoped(): Builder
    {
        return $this->model->newQuery()->where('school_id', $this->getCurrentSchoolId());
    }

    public function find(int $id): ?Model
    {
        return $this->schoolScoped()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->schoolScoped()->findOrFail($id);
    }

    public function create(array $data): Model
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

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

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = $this->schoolScoped();
        $query = $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}

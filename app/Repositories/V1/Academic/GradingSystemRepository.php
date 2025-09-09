<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\Academic\GradingSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GradingSystemRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return GradingSystem::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('system_type', 'like', "%{$search}%");
        });
    }

    /**
     * Apply additional filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['system_type'])) {
            $query->where('system_type', $filters['system_type']);
        }

        if (isset($filters['is_primary'])) {
            $query->where('is_primary', $filters['is_primary']);
        }

        return $query;
    }

    /**
     * Get primary grading system
     */
    public function getPrimary(): ?GradingSystem
    {
        return $this->schoolScoped()
            ->where('is_primary', true)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Clear primary flags for all grading systems
     */
    public function clearPrimaryFlags(): void
    {
        $this->schoolScoped()->update(['is_primary' => false]);
    }

    /**
     * Get grading systems with grade scales
     */
    public function getWithScales(): Collection
    {
        return $this->schoolScoped()
            ->with(['gradeScales.gradeLevels' => function ($query) {
                $query->orderBy('sort_order');
            }])
            ->where('status', 'active')
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get grading systems by type
     */
    public function getByType(string $type): Collection
    {
        return $this->schoolScoped()
            ->where('system_type', $type)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get grading systems for grade level
     */
    public function getForGradeLevel(string $gradeLevel): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where(function ($query) use ($gradeLevel) {
                $query->whereJsonContains('applicable_grades', $gradeLevel)
                      ->orWhereNull('applicable_grades')
                      ->orWhere('applicable_grades', '[]');
            })
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get grading systems for subject
     */
    public function getForSubject(string $subjectArea): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where(function ($query) use ($subjectArea) {
                $query->whereJsonContains('applicable_subjects', $subjectArea)
                      ->orWhereNull('applicable_subjects')
                      ->orWhere('applicable_subjects', '[]');
            })
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get grading system statistics
     */
    public function getStatistics(): array
    {
        $query = $this->schoolScoped();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'by_type' => $query->where('status', 'active')
                ->groupBy('system_type')
                ->selectRaw('system_type, count(*) as count')
                ->pluck('count', 'system_type')
                ->toArray(),
            'primary' => $query->where('is_primary', true)->value('name')
        ];
    }
}

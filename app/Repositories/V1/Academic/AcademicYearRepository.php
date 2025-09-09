<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\SIS\School\AcademicYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AcademicYearRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return AcademicYear::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%");
        });
    }

    /**
     * Get current academic year
     */
    public function getCurrent(): ?AcademicYear
    {
        return $this->schoolScoped()->where('is_current', true)->first();
    }

    /**
     * Clear current flags for all academic years
     */
    public function clearCurrentFlags(): void
    {
        $this->schoolScoped()->update(['is_current' => false]);
    }

    /**
     * Get academic years for selection (active and completed)
     */
    public function getForSelection(): Collection
    {
        return $this->schoolScoped()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code', 'start_date', 'end_date', 'is_current']);
    }

    /**
     * Find overlapping academic years
     */
    public function findOverlapping(string $startDate, string $endDate, ?int $excludeId = null): ?AcademicYear
    {
        $query = $this->schoolScoped()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    /**
     * Get academic years by status
     */
    public function getByStatus(string $status): Collection
    {
        return $this->schoolScoped()->where('status', $status)->get();
    }

    /**
     * Get academic years by term structure
     */
    public function getByTermStructure(string $termStructure): Collection
    {
        return $this->schoolScoped()->where('term_structure', $termStructure)->get();
    }

    /**
     * Get academic year statistics
     */
    public function getStatistics(): array
    {
        $query = $this->schoolScoped();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'by_structure' => $query->groupBy('term_structure')
                ->selectRaw('term_structure, count(*) as count')
                ->pluck('count', 'term_structure')
                ->toArray()
        ];
    }
}

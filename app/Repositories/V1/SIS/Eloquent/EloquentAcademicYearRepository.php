<?php

namespace App\Repositories\V1\SIS\Eloquent;

use App\Models\V1\SIS\School\AcademicYear;
use App\Repositories\V1\SIS\Contracts\AcademicYearRepositoryInterface;
use App\Services\SchoolContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Eloquent Academic Year Repository
 *
 * Implementation of the AcademicYearRepositoryInterface using Eloquent ORM
 * with automatic school context scoping and academic year business logic.
 */
class EloquentAcademicYearRepository implements AcademicYearRepositoryInterface
{
    protected AcademicYear $model;
    protected SchoolContextService $schoolContext;

    public function __construct(AcademicYear $model, SchoolContextService $schoolContext)
    {
        $this->model = $model;
        $this->schoolContext = $schoolContext;
    }

    /**
     * Get a new query builder with school scoping applied.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('school_id', $this->schoolContext->getCurrentSchoolId());
    }

    /**
     * Find academic year by ID.
     */
    public function find(int $id): ?AcademicYear
    {
        return $this->newQuery()->find($id);
    }

    /**
     * Find academic year by code within school.
     */
    public function findByCode(string $code, int $schoolId): ?AcademicYear
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->first();
    }

    /**
     * Create a new academic year record.
     */
    public function create(array $data): AcademicYear
    {
        $data['school_id'] = $this->schoolContext->getCurrentSchoolId();

        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = $this->generateAcademicYearCode($data['name'] ?? '');
        }

        return $this->model->create($data);
    }

    /**
     * Update an existing academic year record.
     */
    public function update(int $id, array $data): AcademicYear
    {
        $academicYear = $this->newQuery()->findOrFail($id);

        $academicYear->update($data);

        return $academicYear->fresh();
    }

    /**
     * Delete an academic year record.
     */
    public function delete(int $id): bool
    {
        $academicYear = $this->newQuery()->findOrFail($id);

        return $academicYear->delete();
    }

    /**
     * Get paginated list of academic years with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'school',
            'terms'
        ]);

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Get academic years by school.
     */
    public function getBySchool(int $schoolId): Collection
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->with(['terms'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Get current academic year for school.
     */
    public function getCurrent(int $schoolId): ?AcademicYear
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->with(['terms'])
            ->first();
    }

    /**
     * Set current academic year for school.
     */
    public function setCurrent(int $academicYearId, int $schoolId): bool
    {
        // First, unset current flag for all academic years in the school
        $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->update(['is_current' => false]);

        // Then set the specified academic year as current
        return $this->model->newQuery()
            ->where('id', $academicYearId)
            ->where('school_id', $schoolId)
            ->update(['is_current' => true]);
    }

    /**
     * Get academic years by status.
     */
    public function getByStatus(string $status, int $schoolId): Collection
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('status', $status)
            ->with(['terms'])
            ->get();
    }

    /**
     * Get active academic years.
     */
    public function getActive(int $schoolId): Collection
    {
        return $this->getByStatus('active', $schoolId);
    }

    /**
     * Get planned academic years.
     */
    public function getPlanned(int $schoolId): Collection
    {
        return $this->getByStatus('planning', $schoolId);
    }

    /**
     * Get completed academic years.
     */
    public function getCompleted(int $schoolId): Collection
    {
        return $this->getByStatus('completed', $schoolId);
    }

    /**
     * Get academic years within date range.
     */
    public function getInDateRange(string $startDate, string $endDate, int $schoolId): Collection
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                          $subQuery->where('start_date', '<=', $startDate)
                                   ->where('end_date', '>=', $endDate);
                      });
            })
            ->with(['terms'])
            ->get();
    }

    /**
     * Get academic year containing a specific date.
     */
    public function getContainingDate(string $date, int $schoolId): ?AcademicYear
    {
        $targetDate = Carbon::parse($date);

        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('start_date', '<=', $targetDate)
            ->where('end_date', '>=', $targetDate)
            ->with(['terms'])
            ->first();
    }

    /**
     * Get next academic year.
     */
    public function getNext(int $academicYearId): ?AcademicYear
    {
        $currentYear = $this->model->findOrFail($academicYearId);

        return $this->model->newQuery()
            ->where('school_id', $currentYear->school_id)
            ->where('start_date', '>', $currentYear->end_date)
            ->orderBy('start_date')
            ->first();
    }

    /**
     * Get previous academic year.
     */
    public function getPrevious(int $academicYearId): ?AcademicYear
    {
        $currentYear = $this->model->findOrFail($academicYearId);

        return $this->model->newQuery()
            ->where('school_id', $currentYear->school_id)
            ->where('end_date', '<', $currentYear->start_date)
            ->orderBy('end_date', 'desc')
            ->first();
    }

    /**
     * Update academic year status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $academicYear = $this->newQuery()->findOrFail($id);

        return $academicYear->update(['status' => $status]);
    }

    /**
     * Check if academic year code is available within school.
     */
    public function isCodeAvailable(string $code, int $schoolId): bool
    {
        return !$this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->exists();
    }

    /**
     * Get academic year statistics.
     */
    public function getStatistics(int $academicYearId): array
    {
        $academicYear = $this->model->findOrFail($academicYearId);

        return [
            'name' => $academicYear->name,
            'code' => $academicYear->code,
            'start_date' => $academicYear->start_date,
            'end_date' => $academicYear->end_date,
            'status' => $academicYear->status,
            'is_current' => $academicYear->is_current,
            'duration_days' => $academicYear->getDurationInDays(),
            'terms_count' => $academicYear->terms()->count(),
            'active_terms_count' => $academicYear->terms()->where('status', 'active')->count(),
            'total_instructional_days' => $academicYear->total_instructional_days,
        ];
    }

    /**
     * Apply filters to query builder.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['is_current'])) {
            $query->where('is_current', $filters['is_current']);
        }

        if (!empty($filters['term_structure'])) {
            $query->where('term_structure', $filters['term_structure']);
        }

        return $query;
    }

    /**
     * Generate academic year code from name.
     */
    protected function generateAcademicYearCode(string $name): string
    {
        // Extract year from name like "2025-2026" -> "AY2025"
        if (preg_match('/(\d{4})/', $name, $matches)) {
            return 'AY' . $matches[1];
        }

        // Fallback: generate based on current year
        return 'AY' . date('Y');
    }
}

<?php

namespace App\Repositories\V1\SIS\Eloquent;

use App\Models\V1\SIS\School\AcademicTerm;
use App\Repositories\V1\SIS\Contracts\AcademicTermRepositoryInterface;
use App\Services\SchoolContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Eloquent Academic Term Repository
 *
 * Implementation of the AcademicTermRepositoryInterface using Eloquent ORM
 * with automatic school context scoping and academic term business logic.
 */
class EloquentAcademicTermRepository implements AcademicTermRepositoryInterface
{
    protected AcademicTerm $model;
    protected SchoolContextService $schoolContext;

    public function __construct(AcademicTerm $model, SchoolContextService $schoolContext)
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
     * Find academic term by ID.
     */
    public function find(int $id): ?AcademicTerm
    {
        return $this->newQuery()->with(['academicYear', 'school'])->find($id);
    }

    /**
     * Create a new academic term record.
     */
    public function create(array $data): AcademicTerm
    {
        $data['school_id'] = $this->schoolContext->getCurrentSchoolId();

        // Generate term number if not provided
        if (empty($data['term_number'])) {
            $data['term_number'] = $this->generateTermNumber($data['academic_year_id'] ?? 0);
        }

        return $this->model->create($data);
    }

    /**
     * Update an existing academic term record.
     */
    public function update(int $id, array $data): AcademicTerm
    {
        $term = $this->newQuery()->findOrFail($id);

        $term->update($data);

        return $term->fresh();
    }

    /**
     * Delete an academic term record.
     */
    public function delete(int $id): bool
    {
        $term = $this->newQuery()->findOrFail($id);

        return $term->delete();
    }

    /**
     * Get paginated list of academic terms with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'academicYear',
            'school'
        ]);

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Get terms by academic year.
     */
    public function getByAcademicYear(int $academicYearId): Collection
    {
        return $this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->with(['academicYear', 'school'])
            ->orderBy('term_number')
            ->get();
    }

    /**
     * Get terms by school.
     */
    public function getBySchool(int $schoolId): Collection
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->with(['academicYear', 'school'])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get active terms.
     */
    public function getActive(int $schoolId): Collection
    {
        return $this->getByStatus('active', $schoolId);
    }

    /**
     * Get planned terms.
     */
    public function getPlanned(int $schoolId): Collection
    {
        return $this->getByStatus('planned', $schoolId);
    }

    /**
     * Get completed terms.
     */
    public function getCompleted(int $schoolId): Collection
    {
        return $this->getByStatus('completed', $schoolId);
    }

    /**
     * Get terms by status.
     */
    public function getByStatus(string $status, int $schoolId): Collection
    {
        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('status', $status)
            ->with(['academicYear', 'school'])
            ->orderBy('term_number')
            ->get();
    }

    /**
     * Get terms within date range.
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
            ->with(['academicYear', 'school'])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get term containing a specific date.
     */
    public function getContainingDate(string $date, int $schoolId): ?AcademicTerm
    {
        $targetDate = Carbon::parse($date);

        return $this->model->newQuery()
            ->where('school_id', $schoolId)
            ->where('start_date', '<=', $targetDate)
            ->where('end_date', '>=', $targetDate)
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Get next term.
     */
    public function getNext(int $termId): ?AcademicTerm
    {
        $currentTerm = $this->model->findOrFail($termId);

        return $this->model->newQuery()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->where('term_number', '>', $currentTerm->term_number)
            ->orderBy('term_number')
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Get previous term.
     */
    public function getPrevious(int $termId): ?AcademicTerm
    {
        $currentTerm = $this->model->findOrFail($termId);

        return $this->model->newQuery()
            ->where('academic_year_id', $currentTerm->academic_year_id)
            ->where('term_number', '<', $currentTerm->term_number)
            ->orderBy('term_number', 'desc')
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Update term status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $term = $this->newQuery()->findOrFail($id);

        return $term->update(['status' => $status]);
    }

    /**
     * Get first term in academic year.
     */
    public function getFirstTerm(int $academicYearId): ?AcademicTerm
    {
        return $this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->orderBy('term_number')
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Get last term in academic year.
     */
    public function getLastTerm(int $academicYearId): ?AcademicTerm
    {
        return $this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->orderBy('term_number', 'desc')
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Get term by number in academic year.
     */
    public function getByNumber(int $academicYearId, int $termNumber): ?AcademicTerm
    {
        return $this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->where('term_number', $termNumber)
            ->with(['academicYear', 'school'])
            ->first();
    }

    /**
     * Check if term number is available in academic year.
     */
    public function isTermNumberAvailable(int $academicYearId, int $termNumber): bool
    {
        return !$this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->where('term_number', $termNumber)
            ->exists();
    }

    /**
     * Get term statistics.
     */
    public function getStatistics(int $termId): array
    {
        $term = $this->model->findOrFail($termId);

        return [
            'name' => $term->name,
            'term_number' => $term->term_number,
            'start_date' => $term->start_date,
            'end_date' => $term->end_date,
            'status' => $term->status,
            'instructional_days' => $term->instructional_days,
            'duration_days' => $term->getDurationInDays(),
            'is_first_term' => $term->isFirstTerm(),
            'is_last_term' => $term->isLastTerm(),
            'academic_year' => [
                'id' => $term->academicYear->id,
                'name' => $term->academicYear->name,
                'code' => $term->academicYear->code,
            ],
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

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (!empty($filters['term_number'])) {
            $query->where('term_number', $filters['term_number']);
        }

        return $query;
    }

    /**
     * Generate term number for academic year.
     */
    protected function generateTermNumber(int $academicYearId): int
    {
        $maxTermNumber = $this->model->newQuery()
            ->where('academic_year_id', $academicYearId)
            ->max('term_number');

        return ($maxTermNumber ?? 0) + 1;
    }
}

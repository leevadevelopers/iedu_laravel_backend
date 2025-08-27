<?php

namespace App\Repositories\V1\SIS\Contracts;

use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Academic Term Repository Interface
 *
 * Defines the contract for academic term data access operations
 * including CRUD operations and academic year relationship management.
 */
interface AcademicTermRepositoryInterface
{
    /**
     * Find academic term by ID.
     */
    public function find(int $id): ?AcademicTerm;

    /**
     * Create a new academic term record.
     */
    public function create(array $data): AcademicTerm;

    /**
     * Update an existing academic term record.
     */
    public function update(int $id, array $data): AcademicTerm;

    /**
     * Delete an academic term record.
     */
    public function delete(int $id): bool;

    /**
     * Get paginated list of academic terms with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get terms by academic year.
     */
    public function getByAcademicYear(int $academicYearId): Collection;

    /**
     * Get terms by school.
     */
    public function getBySchool(int $schoolId): Collection;

    /**
     * Get active terms.
     */
    public function getActive(int $schoolId): Collection;

    /**
     * Get planned terms.
     */
    public function getPlanned(int $schoolId): Collection;

    /**
     * Get completed terms.
     */
    public function getCompleted(int $schoolId): Collection;

    /**
     * Get terms by status.
     */
    public function getByStatus(string $status, int $schoolId): Collection;

    /**
     * Get terms within date range.
     */
    public function getInDateRange(string $startDate, string $endDate, int $schoolId): Collection;

    /**
     * Get term containing a specific date.
     */
    public function getContainingDate(string $date, int $schoolId): ?AcademicTerm;

    /**
     * Get next term.
     */
    public function getNext(int $termId): ?AcademicTerm;

    /**
     * Get previous term.
     */
    public function getPrevious(int $termId): ?AcademicTerm;

    /**
     * Update term status.
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Get first term in academic year.
     */
    public function getFirstTerm(int $academicYearId): ?AcademicTerm;

    /**
     * Get last term in academic year.
     */
    public function getLastTerm(int $academicYearId): ?AcademicTerm;

    /**
     * Get term by number in academic year.
     */
    public function getByNumber(int $academicYearId, int $termNumber): ?AcademicTerm;

    /**
     * Check if term number is available in academic year.
     */
    public function isTermNumberAvailable(int $academicYearId, int $termNumber): bool;

    /**
     * Get term statistics.
     */
    public function getStatistics(int $termId): array;
}

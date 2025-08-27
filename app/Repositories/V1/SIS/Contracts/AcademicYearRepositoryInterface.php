<?php

namespace App\Repositories\V1\SIS\Contracts;

use App\Models\V1\SIS\School\AcademicYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Academic Year Repository Interface
 *
 * Defines the contract for academic year data access operations
 * including CRUD operations, term management, and date-based queries.
 */
interface AcademicYearRepositoryInterface
{
    /**
     * Find academic year by ID.
     */
    public function find(int $id): ?AcademicYear;

    /**
     * Find academic year by code within school.
     */
    public function findByCode(string $code, int $schoolId): ?AcademicYear;

    /**
     * Create a new academic year record.
     */
    public function create(array $data): AcademicYear;

    /**
     * Update an existing academic year record.
     */
    public function update(int $id, array $data): AcademicYear;

    /**
     * Delete an academic year record.
     */
    public function delete(int $id): bool;

    /**
     * Get paginated list of academic years with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get academic years by school.
     */
    public function getBySchool(int $schoolId): Collection;

    /**
     * Get current academic year for school.
     */
    public function getCurrent(int $schoolId): ?AcademicYear;

    /**
     * Set current academic year for school.
     */
    public function setCurrent(int $academicYearId, int $schoolId): bool;

    /**
     * Get academic years by status.
     */
    public function getByStatus(string $status, int $schoolId): Collection;

    /**
     * Get active academic years.
     */
    public function getActive(int $schoolId): Collection;

    /**
     * Get planned academic years.
     */
    public function getPlanned(int $schoolId): Collection;

    /**
     * Get completed academic years.
     */
    public function getCompleted(int $schoolId): Collection;

    /**
     * Get academic years within date range.
     */
    public function getInDateRange(string $startDate, string $endDate, int $schoolId): Collection;

    /**
     * Get academic year containing a specific date.
     */
    public function getContainingDate(string $date, int $schoolId): ?AcademicYear;

    /**
     * Get next academic year.
     */
    public function getNext(int $academicYearId): ?AcademicYear;

    /**
     * Get previous academic year.
     */
    public function getPrevious(int $academicYearId): ?AcademicYear;

    /**
     * Update academic year status.
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Check if academic year code is available within school.
     */
    public function isCodeAvailable(string $code, int $schoolId): bool;

    /**
     * Get academic year statistics.
     */
    public function getStatistics(int $academicYearId): array;
}

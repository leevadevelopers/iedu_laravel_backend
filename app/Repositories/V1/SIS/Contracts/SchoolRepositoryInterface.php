<?php

namespace App\Repositories\V1\SIS\Contracts;

use App\Models\V1\SIS\School\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * School Repository Interface
 *
 * Defines the contract for school data access operations
 * including CRUD operations, configuration management, and multi-tenant queries.
 */
interface SchoolRepositoryInterface
{
    /**
     * Find school by ID.
     */
    public function find(int $id): ?School;

    /**
     * Find school by code.
     */
    public function findByCode(string $schoolCode): ?School;

    /**
     * Create a new school record.
     */
    public function create(array $data): School;

    /**
     * Update an existing school record.
     */
    public function update(int $id, array $data): School;

    /**
     * Delete a school record.
     */
    public function delete(int $id): bool;

    /**
     * Get paginated list of schools with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Search schools by name, code, or other criteria.
     */
    public function search(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get schools by type.
     */
    public function getByType(string $type): Collection;

    /**
     * Get schools by country.
     */
    public function getByCountry(string $countryCode): Collection;

    /**
     * Get schools by accreditation status.
     */
    public function getByAccreditationStatus(string $status): Collection;

    /**
     * Get active schools.
     */
    public function getActive(): Collection;

    /**
     * Get schools in setup mode.
     */
    public function getInSetup(): Collection;

    /**
     * Get schools on trial.
     */
    public function getOnTrial(): Collection;

    /**
     * Get schools with capacity.
     */
    public function getWithCapacity(): Collection;

    /**
     * Update school enrollment count.
     */
    public function updateEnrollmentCount(int $schoolId, int $count): bool;

    /**
     * Update school staff count.
     */
    public function updateStaffCount(int $schoolId, int $count): bool;

    /**
     * Get school statistics.
     */
    public function getStatistics(int $schoolId): array;

    /**
     * Check if school code is available.
     */
    public function isSchoolCodeAvailable(string $schoolCode): bool;

    /**
     * Get schools by subscription plan.
     */
    public function getBySubscriptionPlan(string $plan): Collection;

    /**
     * Get schools requiring onboarding completion.
     */
    public function getRequiringOnboarding(): Collection;
}

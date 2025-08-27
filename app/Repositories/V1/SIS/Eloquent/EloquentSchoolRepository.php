<?php

namespace App\Repositories\V1\SIS\Eloquent;

use App\Models\V1\SIS\School\School;
use App\Repositories\V1\SIS\Contracts\SchoolRepositoryInterface;
use App\Services\SchoolContextService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent School Repository
 *
 * Implementation of the SchoolRepositoryInterface using Eloquent ORM
 * with automatic tenant context scoping and school business logic.
 */
class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    protected School $model;
    protected SchoolContextService $schoolContext;

    public function __construct(School $model, SchoolContextService $schoolContext)
    {
        $this->model = $model;
        $this->schoolContext = $schoolContext;
    }

    /**
     * Get a new query builder with tenant scoping applied.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('tenant_id', $this->schoolContext->getCurrentTenantId());
    }

    /**
     * Find school by ID.
     */
    public function find(int $id): ?School
    {
        return $this->newQuery()->find($id);
    }

    /**
     * Find school by code.
     */
    public function findByCode(string $schoolCode): ?School
    {
        return $this->newQuery()
            ->where('school_code', $schoolCode)
            ->first();
    }

    /**
     * Create a new school record.
     */
    public function create(array $data): School
    {
        $data['tenant_id'] = $this->schoolContext->getCurrentTenantId();

        // Generate school code if not provided
        if (empty($data['school_code'])) {
            $data['school_code'] = $this->generateSchoolCode();
        }

        return $this->model->create($data);
    }

    /**
     * Update an existing school record.
     */
    public function update(int $id, array $data): School
    {
        $school = $this->newQuery()->findOrFail($id);

        $school->update($data);

        return $school->fresh();
    }

    /**
     * Delete a school record.
     */
    public function delete(int $id): bool
    {
        $school = $this->newQuery()->findOrFail($id);

        return $school->delete();
    }

    /**
     * Get paginated list of schools with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'tenant',
            'academicYears',
            'users'
        ]);

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * Search schools by name, code, or other criteria.
     */
    public function search(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $queryBuilder = $this->newQuery()->with([
            'tenant',
            'academicYears',
            'users'
        ]);

        $queryBuilder->where(function ($q) use ($query) {
            $q->where('official_name', 'like', "%{$query}%")
              ->orWhere('display_name', 'like', "%{$query}%")
              ->orWhere('short_name', 'like', "%{$query}%")
              ->orWhere('school_code', 'like', "%{$query}%")
              ->orWhere('city', 'like', "%{$query}%");
        });

        return $this->applyFilters($queryBuilder, $filters)->paginate($perPage);
    }

    /**
     * Get schools by type.
     */
    public function getByType(string $type): Collection
    {
        return $this->newQuery()
            ->where('school_type', $type)
            ->get();
    }

    /**
     * Get schools by country.
     */
    public function getByCountry(string $countryCode): Collection
    {
        return $this->newQuery()
            ->where('country_code', $countryCode)
            ->get();
    }

    /**
     * Get schools by accreditation status.
     */
    public function getByAccreditationStatus(string $status): Collection
    {
        return $this->newQuery()
            ->where('accreditation_status', $status)
            ->get();
    }

    /**
     * Get active schools.
     */
    public function getActive(): Collection
    {
        return $this->newQuery()
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get schools in setup mode.
     */
    public function getInSetup(): Collection
    {
        return $this->newQuery()
            ->where('status', 'setup')
            ->get();
    }

    /**
     * Get schools on trial.
     */
    public function getOnTrial(): Collection
    {
        return $this->newQuery()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->get();
    }

    /**
     * Get schools with capacity.
     */
    public function getWithCapacity(): Collection
    {
        return $this->newQuery()
            ->whereNotNull('student_capacity')
            ->whereRaw('current_enrollment < student_capacity')
            ->get();
    }

    /**
     * Update school enrollment count.
     */
    public function updateEnrollmentCount(int $schoolId, int $count): bool
    {
        $school = $this->newQuery()->findOrFail($schoolId);

        return $school->update(['current_enrollment' => $count]);
    }

    /**
     * Update school staff count.
     */
    public function updateStaffCount(int $schoolId, int $count): bool
    {
        $school = $this->newQuery()->findOrFail($schoolId);

        return $school->update(['staff_count' => $count]);
    }

    /**
     * Get school statistics.
     */
    public function getStatistics(int $schoolId): array
    {
        $school = $this->newQuery()->findOrFail($schoolId);

        return [
            'total_students' => $school->current_enrollment,
            'staff_count' => $school->staff_count,
            'capacity' => $school->student_capacity,
            'enrollment_percentage' => $school->getEnrollmentPercentage(),
            'academic_years_count' => $school->academicYears()->count(),
            'users_count' => $school->users()->count(),
            'status' => $school->status,
            'established_date' => $school->established_date,
            'onboarding_completed' => $school->isOnboardingCompleted(),
        ];
    }

    /**
     * Check if school code is available.
     */
    public function isSchoolCodeAvailable(string $schoolCode): bool
    {
        return !$this->newQuery()
            ->where('school_code', $schoolCode)
            ->exists();
    }

    /**
     * Get schools by subscription plan.
     */
    public function getBySubscriptionPlan(string $plan): Collection
    {
        return $this->newQuery()
            ->where('subscription_plan', $plan)
            ->get();
    }

    /**
     * Get schools requiring onboarding completion.
     */
    public function getRequiringOnboarding(): Collection
    {
        return $this->newQuery()
            ->where('status', 'setup')
            ->whereNull('onboarding_completed_at')
            ->get();
    }

    /**
     * Apply filters to query builder.
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['type'])) {
            $query->where('school_type', $filters['type']);
        }

        if (!empty($filters['country'])) {
            $query->where('country_code', $filters['country']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['accreditation'])) {
            $query->where('accreditation_status', $filters['accreditation']);
        }

        if (!empty($filters['subscription_plan'])) {
            $query->where('subscription_plan', $filters['subscription_plan']);
        }

        return $query;
    }

    /**
     * Generate unique school code.
     */
    protected function generateSchoolCode(): string
    {
        $prefix = 'SCH';
        $counter = 1;

        do {
            $code = $prefix . str_pad($counter, 6, '0', STR_PAD_LEFT);
            $counter++;
        } while (!$this->isSchoolCodeAvailable($code));

        return $code;
    }
}

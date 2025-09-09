<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\Academic\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TeacherRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return Teacher::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('department', 'like', "%{$search}%")
              ->orWhere('position', 'like', "%{$search}%");
        });
    }

    /**
     * Apply additional filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (isset($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['specialization'])) {
            $query->whereJsonContains('specializations_json', $filters['specialization']);
        }

        if (isset($filters['grade_level'])) {
            $query->whereJsonContains('specializations_json', $filters['grade_level']);
        }

        if (isset($filters['has_classes'])) {
            if ($filters['has_classes']) {
                $query->whereHas('classes');
            } else {
                $query->whereDoesntHave('classes');
            }
        }

        return $query;
    }

    /**
     * Get teachers with relationships
     */
    public function getWithRelationships(): Collection
    {
        return $this->schoolScoped()
            ->with(['user', 'classes.subject', 'classes.academicYear'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by department
     */
    public function getByDepartment(string $department): Collection
    {
        return $this->schoolScoped()
            ->where('department', $department)
            ->where('status', 'active')
            ->with(['user', 'classes'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by employment type
     */
    public function getByEmploymentType(string $employmentType): Collection
    {
        return $this->schoolScoped()
            ->where('employment_type', $employmentType)
            ->where('status', 'active')
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by specialization
     */
    public function getBySpecialization(string $specialization): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('specializations_json', $specialization)
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by grade level
     */
    public function getByGradeLevel(string $gradeLevel): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('specializations_json', $gradeLevel)
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Search teachers
     */
    public function search(string $search): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Check if employee ID exists
     */
    public function employeeIdExists(string $employeeId, ?int $excludeId = null): bool
    {
        $query = $this->schoolScoped()->where('employee_id', $employeeId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->schoolScoped()->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get teachers available at specific time
     */
    public function getAvailableAt(string $day, string $time): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains("schedule_json->{$day}->available_times", $time)
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers with workload statistics
     */
    public function getWithWorkloadStats(): Collection
    {
        return $this->schoolScoped()
            ->withCount(['classes' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withSum('classes', 'current_enrollment')
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by subject specialization
     */
    public function getBySubjectSpecialization(string $subject): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('specializations_json', $subject)
            ->with(['user', 'classes.subject'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers with certification
     */
    public function getWithCertification(string $certificationType): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('certifications_json', ['type' => $certificationType])
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by years of service
     */
    public function getByYearsOfService(int $minYears, ?int $maxYears = null): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->whereRaw('DATEDIFF(CURDATE(), hire_date) >= ?', [$minYears * 365]);

        if ($maxYears) {
            $query->whereRaw('DATEDIFF(CURDATE(), hire_date) <= ?', [$maxYears * 365]);
        }

        return $query->with(['user'])
            ->orderBy('hire_date')
            ->get();
    }

    /**
     * Get teachers with performance metrics
     */
    public function getWithPerformanceMetrics(?int $academicTermId = null): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->withCount(['gradeEntries' => function ($query) use ($academicTermId) {
                if ($academicTermId) {
                    $query->where('academic_term_id', $academicTermId);
                }
            }])
            ->withCount(['classes' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withSum('classes', 'current_enrollment');

        if ($academicTermId) {
            $query->withCount(['gradeEntries' => function ($query) use ($academicTermId) {
                $query->where('academic_term_id', $academicTermId);
            }]);
        }

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teacher statistics
     */
    public function getStatistics(): array
    {
        $query = $this->schoolScoped();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'by_department' => $query->where('status', 'active')
                ->groupBy('department')
                ->selectRaw('department, count(*) as count')
                ->pluck('count', 'department')
                ->toArray(),
            'by_employment_type' => $query->where('status', 'active')
                ->groupBy('employment_type')
                ->selectRaw('employment_type, count(*) as count')
                ->pluck('count', 'employment_type')
                ->toArray(),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'average_years_service' => $query->where('status', 'active')
                ->selectRaw('AVG(DATEDIFF(CURDATE(), hire_date) / 365) as avg_years')
                ->value('avg_years')
        ];
    }

    /**
     * Get teachers for class assignment
     */
    public function getForClassAssignment(int $subjectId, string $gradeLevel): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where(function ($query) use ($subjectId, $gradeLevel) {
                $query->whereJsonContains('specializations_json', $gradeLevel)
                      ->orWhereJsonContains('specializations_json', $subjectId);
            })
            ->with(['user', 'classes' => function ($query) {
                $query->where('status', 'active');
            }])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers with schedule conflicts
     */
    public function getWithScheduleConflicts(string $day, string $time, ?int $excludeId = null): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains("schedule_json->{$day}->available_times", $time);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teachers by hire date range
     */
    public function getByHireDateRange(string $startDate, string $endDate): Collection
    {
        return $this->schoolScoped()
            ->whereBetween('hire_date', [$startDate, $endDate])
            ->with(['user'])
            ->orderBy('hire_date', 'desc')
            ->get();
    }

    /**
     * Get teachers with upcoming certifications expiry
     */
    public function getWithExpiringCertifications(int $daysAhead = 30): Collection
    {
        $expiryDate = now()->addDays($daysAhead)->toDateString();

        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('certifications_json', function ($query) use ($expiryDate) {
                $query->where('expiry_date', '<=', $expiryDate)
                      ->where('expiry_date', '>', now()->toDateString());
            })
            ->with(['user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}

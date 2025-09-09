#!/bin/bash

# iEDU Academic Management - Repositories Generation
# Creates all Laravel repositories for the Academic Management module

echo "🗃️ Creating iEDU Academic Management Repositories..."

# Create Repositories directory if not exists
mkdir -p app/Repositories/V1/Academic

#1. Base Academic Repository
cat > app/Repositories/V1/Academic/BaseAcademicRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Services\SchoolContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseAcademicRepository
{
    protected Model $model;
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
        $this->model = app($this->getModelClass());
    }

    /**
     * Get the model class name
     */
    abstract protected function getModelClass(): string;

    /**
     * Get current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return $this->schoolContextService->getCurrentSchool()->id;
    }

    /**
     * Apply school scope to query
     */
    protected function schoolScoped(): Builder
    {
        return $this->model->where('school_id', $this->getCurrentSchoolId());
    }

    /**
     * Find by ID with school scope
     */
    public function find(int $id): ?Model
    {
        return $this->schoolScoped()->find($id);
    }

    /**
     * Find by ID or fail with school scope
     */
    public function findOrFail(int $id): Model
    {
        return $this->schoolScoped()->findOrFail($id);
    }

    /**
     * Get all records with school scope
     */
    public function all(): Collection
    {
        return $this->schoolScoped()->get();
    }

    /**
     * Create new record
     */
    public function create(array $data): Model
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        return $this->model->create($data);
    }

    /**
     * Update existing record
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    /**
     * Delete record
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Get count with school scope
     */
    public function count(): int
    {
        return $this->schoolScoped()->count();
    }

    /**
     * Apply common filters to query
     */
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

    /**
     * Apply search filter (override in child classes)
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

    /**
     * Get paginated results with filters
     */
    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = $this->schoolScoped();
        $query = $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
EOF

#2. Teacher Repository
cat > app/Repositories/V1/Academic/TeacherRepository.php << 'EOF'
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
EOF

#3. Academic Year Repository
cat > app/Repositories/V1/Academic/AcademicYearRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Models\Academic\AcademicYear;
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
EOF

#3. Subject Repository
cat > app/Repositories/V1/Academic/SubjectRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Models\Academic\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SubjectRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return Subject::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Get subjects by grade level
     */
    public function getByGradeLevel(string $gradeLevel): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->whereJsonContains('grade_levels', $gradeLevel)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get core subjects
     */
    public function getCoreSubjects(): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where('is_core_subject', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get elective subjects
     */
    public function getElectiveSubjects(): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where('is_elective', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get subjects by area
     */
    public function getByArea(string $area): Collection
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->where('subject_area', $area)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if subject code exists
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = $this->schoolScoped()->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get subjects with class count
     */
    public function getWithClassCount(): Collection
    {
        return $this->schoolScoped()
            ->withCount(['classes' => function ($query) {
                $query->where('status', 'active');
            }])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get subject statistics by area
     */
    public function getStatsByArea(): array
    {
        return $this->schoolScoped()
            ->where('status', 'active')
            ->groupBy('subject_area')
            ->selectRaw('subject_area, count(*) as count')
            ->pluck('count', 'subject_area')
            ->toArray();
    }

    /**
     * Get subject statistics by grade
     */
    public function getStatsByGrade(): array
    {
        $subjects = $this->schoolScoped()
            ->where('status', 'active')
            ->get(['grade_levels']);

        $stats = [];
        foreach ($subjects as $subject) {
            foreach ($subject->grade_levels ?? [] as $grade) {
                $stats[$grade] = ($stats[$grade] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /**
     * Get subjects for specific grades and areas
     */
    public function getForCurriculum(array $gradeLevels, ?array $areas = null): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active');

        // Filter by grade levels
        foreach ($gradeLevels as $grade) {
            $query->whereJsonContains('grade_levels', $grade);
        }

        // Filter by subject areas if provided
        if ($areas) {
            $query->whereIn('subject_area', $areas);
        }

        return $query->orderBy('subject_area')->orderBy('name')->get();
    }
}
EOF

#4. Academic Class Repository
cat > app/Repositories/V1/Academic/AcademicClassRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Models\Academic\AcademicClass;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AcademicClassRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return AcademicClass::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('class_code', 'like', "%{$search}%")
              ->orWhere('section', 'like', "%{$search}%")
              ->orWhereHas('subject', function ($sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('primaryTeacher', function ($tq) use ($search) {
                  $tq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Apply additional filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['teacher_id'])) {
            $query->where('primary_teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }

        return $query;
    }

    /**
     * Get classes with relationships
     */
    public function getWithRelationships(): Collection
    {
        return $this->schoolScoped()
            ->with(['subject', 'primaryTeacher', 'academicYear', 'academicTerm'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get classes by teacher
     */
    public function getByTeacher(int $teacherId, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('primary_teacher_id', $teacherId)
            ->with(['subject', 'academicYear', 'academicTerm']);

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get classes by grade level
     */
    public function getByGradeLevel(string $gradeLevel): Collection
    {
        return $this->schoolScoped()
            ->where('grade_level', $gradeLevel)
            ->where('status', 'active')
            ->with(['subject', 'primaryTeacher'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get classes by subject
     */
    public function getBySubject(int $subjectId): Collection
    {
        return $this->schoolScoped()
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->with(['primaryTeacher', 'academicYear', 'academicTerm'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if class code exists
     */
    public function codeExists(string $classCode, ?int $excludeId = null): bool
    {
        $query = $this->schoolScoped()->where('class_code', $classCode);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get classes with enrollment stats
     */
    public function getWithEnrollmentStats(): Collection
    {
        return $this->schoolScoped()
            ->with(['subject', 'primaryTeacher'])
            ->selectRaw('*, (current_enrollment / max_students * 100) as enrollment_percentage')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get class capacity statistics
     */
    public function getCapacityStatistics(): array
    {
        $classes = $this->schoolScoped()->where('status', 'active')->get();

        $totalCapacity = $classes->sum('max_students');
        $totalEnrolled = $classes->sum('current_enrollment');
        $utilizationRate = $totalCapacity > 0 ? ($totalEnrolled / $totalCapacity) * 100 : 0;

        return [
            'total_classes' => $classes->count(),
            'total_capacity' => $totalCapacity,
            'total_enrolled' => $totalEnrolled,
            'utilization_rate' => round($utilizationRate, 2),
            'available_seats' => $totalCapacity - $totalEnrolled,
            'full_classes' => $classes->where('current_enrollment', '>=', $classes->pluck('max_students'))->count(),
            'empty_classes' => $classes->where('current_enrollment', 0)->count()
        ];
    }

    /**
     * Get classes for timetable
     */
    public function getForTimetable(?int $academicYearId = null): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->with(['subject', 'primaryTeacher']);

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->whereNotNull('schedule_json')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get();
    }
}
EOF

#5. Grading System Repository
cat > app/Repositories/V1/Academic/GradingSystemRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Models\Academic\GradingSystem;
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
EOF

#6. Grade Entry Repository
cat > app/Repositories/V1/Academic/GradeEntryRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Academic;

use App\Models\Academic\GradeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GradeEntryRepository extends BaseAcademicRepository
{
    protected function getModelClass(): string
    {
        return GradeEntry::class;
    }

    /**
     * Apply search filter
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('assessment_name', 'like', "%{$search}%")
              ->orWhere('assessment_type', 'like', "%{$search}%")
              ->orWhere('grade_category', 'like', "%{$search}%")
              ->orWhere('letter_grade', 'like', "%{$search}%")
              ->orWhereHas('student', function ($sq) use ($search) {
                  $sq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%")
                     ->orWhere('student_number', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Apply additional filters
     */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }

        if (isset($filters['assessment_type'])) {
            $query->where('assessment_type', $filters['assessment_type']);
        }

        if (isset($filters['grade_category'])) {
            $query->where('grade_category', $filters['grade_category']);
        }

        if (isset($filters['date_from'])) {
            $query->where('assessment_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('assessment_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Get student grades for a specific term
     */
    public function getStudentGrades(int $studentId, int $academicTermId): Collection
    {
        return $this->schoolScoped()
            ->where('student_id', $studentId)
            ->where('academic_term_id', $academicTermId)
            ->with(['class.subject', 'enteredBy'])
            ->orderBy('assessment_date', 'desc')
            ->get();
    }

    /**
     * Get student grades for GPA calculation
     */
    public function getStudentGradesForGPA(int $studentId, int $academicTermId): Collection
    {
        return $this->schoolScoped()
            ->where('student_id', $studentId)
            ->where('academic_term_id', $academicTermId)
            ->whereIn('assessment_type', ['summative', 'exam', 'project']) // Only major assessments for GPA
            ->with('class.subject')
            ->get();
    }

    /**
     * Get class grades for a specific assessment
     */
    public function getClassGrades(int $classId, string $assessmentName): Collection
    {
        return $this->schoolScoped()
            ->where('class_id', $classId)
            ->where('assessment_name', $assessmentName)
            ->with(['student', 'enteredBy'])
            ->orderBy('student.last_name')
            ->orderBy('student.first_name')
            ->get();
    }

    /**
     * Get grade entries by date range
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $classId = null): Collection
    {
        $query = $this->schoolScoped()
            ->whereBetween('assessment_date', [$startDate, $endDate])
            ->with(['student', 'class.subject']);

        if ($classId) {
            $query->where('class_id', $classId);
        }

        return $query->orderBy('assessment_date', 'desc')->get();
    }

    /**
     * Get recent grade entries
     */
    public function getRecent(int $limit = 50): Collection
    {
        return $this->schoolScoped()
            ->with(['student', 'class.subject', 'enteredBy'])
            ->orderBy('entered_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get grade statistics for a class
     */
    public function getClassStatistics(int $classId, ?string $assessmentName = null): array
    {
        $query = $this->schoolScoped()
            ->where('class_id', $classId)
            ->whereNotNull('percentage_score');

        if ($assessmentName) {
            $query->where('assessment_name', $assessmentName);
        }

        $grades = $query->get();

        if ($grades->isEmpty()) {
            return [
                'count' => 0,
                'average' => null,
                'median' => null,
                'min' => null,
                'max' => null,
                'passing_count' => 0,
                'failing_count' => 0,
                'grade_distribution' => []
            ];
        }

        $percentages = $grades->pluck('percentage_score')->sort()->values();
        $passingThreshold = 60; // Configurable

        return [
            'count' => $grades->count(),
            'average' => round($percentages->avg(), 2),
            'median' => $this->calculateMedian($percentages->toArray()),
            'min' => $percentages->min(),
            'max' => $percentages->max(),
            'passing_count' => $grades->where('percentage_score', '>=', $passingThreshold)->count(),
            'failing_count' => $grades->where('percentage_score', '<', $passingThreshold)->count(),
            'grade_distribution' => $grades->groupBy('letter_grade')
                ->map(fn($group) => $group->count())
                ->toArray()
        ];
    }

    /**
     * Get teacher grade entry statistics
     */
    public function getTeacherStatistics(int $teacherId, ?int $academicTermId = null): array
    {
        $query = $this->schoolScoped()
            ->where('entered_by', $teacherId);

        if ($academicTermId) {
            $query->where('academic_term_id', $academicTermId);
        }

        return [
            'total_entries' => $query->count(),
            'recent_entries' => $query->where('entered_at', '>=', now()->subDays(7))->count(),
            'by_assessment_type' => $query->groupBy('assessment_type')
                ->selectRaw('assessment_type, count(*) as count')
                ->pluck('count', 'assessment_type')
                ->toArray(),
            'by_class' => $query->join('classes', 'grade_entries.class_id', '=', 'classes.id')
                ->groupBy('classes.name')
                ->selectRaw('classes.name, count(*) as count')
                ->pluck('count', 'classes.name')
                ->toArray()
        ];
    }

    /**
     * Get grade trend data for student
     */
    public function getStudentTrends(int $studentId, int $subjectId, int $limit = 10): Collection
    {
        return $this->schoolScoped()
            ->where('student_id', $studentId)
            ->whereHas('class', function ($query) use ($subjectId) {
                $query->where('subject_id', $subjectId);
            })
            ->whereNotNull('percentage_score')
            ->orderBy('assessment_date', 'desc')
            ->limit($limit)
            ->get(['assessment_date', 'percentage_score', 'assessment_name', 'assessment_type']);
    }

    /**
     * Bulk insert grade entries
     */
    public function bulkInsert(array $gradeEntries): bool
    {
        return $this->model->insert($gradeEntries);
    }

    /**
     * Calculate median value
     */
    private function calculateMedian(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }
}
EOF

echo "✅ Academic Management Repositories created successfully!"
echo "📁 Repositories created in: app/Repositories/V1/Academic/"
echo "📋 Created repositories:"
echo "   - BaseAcademicRepository"
echo "   - TeacherRepository"
echo "   - AcademicYearRepository"
echo "   - SubjectRepository"
echo "   - AcademicClassRepository"
echo "   - GradingSystemRepository"
echo "   - GradeEntryRepository"
echo "🔧 Next: Create Request classes for validation"

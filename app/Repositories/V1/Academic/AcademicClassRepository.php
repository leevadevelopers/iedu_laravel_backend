<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\Academic\AcademicClass;
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

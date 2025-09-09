<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\Academic\Subject;
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

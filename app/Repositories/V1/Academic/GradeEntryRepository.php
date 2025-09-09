<?php

namespace App\Repositories\V1\Academic;

use App\Models\V1\Academic\GradeEntry;
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

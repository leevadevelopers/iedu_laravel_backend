<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GradeEntryService extends BaseAcademicService
{
    protected GradingSystemService $gradingSystemService;

    public function __construct(GradingSystemService $gradingSystemService)
    {
        $this->gradingSystemService = $gradingSystemService;
    }

    /**
     * Get paginated grade entries with filters
     */
    public function getGradeEntries(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = GradeEntry::where('tenant_id', $user->tenant_id)
            ->where('school_id', $filters['school_id'] ?? $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('assessment_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('letter_grade', 'like', '%' . $filters['search'] . '%');
            });
        }

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

        return $query->with(['student', 'class', 'academicTerm', 'enteredBy', 'modifiedBy'])
            ->orderBy('assessment_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new grade entry
     */
    public function createGradeEntry(array $data)
    {
        $user = Auth::user();

        $data['tenant_id'] = $user->tenant_id;
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['entered_by'] = $user->id;

        // Calculate derived values
        $this->calculateGradeValues($data);

        return GradeEntry::create($data);
    }

    /**
     * Create bulk grade entries
     */
    public function createBulkGradeEntries(array $data): array
    {
        $successful = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($data['grades'] as $gradeData) {
                try {
                    $user = Auth::user();
                    $gradeData['tenant_id'] = $user->tenant_id;
                    $gradeData['school_id'] = $this->getCurrentSchoolId();
                    $gradeData['entered_by'] = $user->id;
                    $gradeData['class_id'] = $data['class_id'];
                    $gradeData['academic_term_id'] = $data['academic_term_id'];
                    $gradeData['assessment_name'] = $data['assessment_name'];
                    $gradeData['assessment_type'] = $data['assessment_type'];
                    $gradeData['assessment_date'] = $data['assessment_date'];

                    $this->calculateGradeValues($gradeData);

                    $gradeEntry = GradeEntry::create($gradeData);
                    $successful[] = $gradeEntry;
                } catch (\Exception $e) {
                    $failed[] = [
                        'student_id' => $gradeData['student_id'] ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['successful' => $successful, 'failed' => $failed];
    }

    /**
     * Update grade entry
     */
    public function updateGradeEntry(GradeEntry $gradeEntry, array $data)
    {
        $this->validateSchoolOwnership($gradeEntry);

        $data['modified_by'] = Auth::user()->id;

        // Recalculate derived values if score changed
        if (isset($data['raw_score']) || isset($data['points_earned']) || isset($data['percentage_score'])) {
            $this->calculateGradeValues($data, $gradeEntry);
        }

        $gradeEntry->update($data);
        return $gradeEntry->fresh();
    }

    /**
     * Delete grade entry
     */
    public function deleteGradeEntry(GradeEntry $gradeEntry): bool
    {
        $this->validateSchoolOwnership($gradeEntry);

        return $gradeEntry->delete();
    }

    /**
     * Get student grades for a term
     */
    public function getStudentGrades(int $studentId, int $academicTermId): Collection
    {
        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        $user = Auth::user();

        return GradeEntry::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('student_id', $studentId)
            ->where('academic_term_id', $academicTermId)
            ->with(['class', 'academicTerm'])
            ->orderBy('assessment_date', 'desc')
            ->get();
    }

    /**
     * Get class grades for an assessment
     */
    public function getClassGrades(int $classId, string $assessmentName): Collection
    {
        $user = Auth::user();

        return GradeEntry::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('class_id', $classId)
            ->where('assessment_name', $assessmentName)
            ->with(['student', 'class'])
            ->orderBy('student_id')
            ->get();
    }

    /**
     * Calculate student GPA for a term
     */
    public function calculateStudentGPA(int $studentId, int $academicTermId): float
    {
        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        $user = Auth::user();

        $gradeEntries = GradeEntry::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('student_id', $studentId)
            ->where('academic_term_id', $academicTermId)
            ->whereNotNull('percentage_score')
            ->get();

        if ($gradeEntries->isEmpty()) {
            return 0.0;
        }

        $gradeData = $gradeEntries->map(function ($entry) {
            return [
                'percentage' => $entry->percentage_score,
                'credits' => $entry->class->subject->credit_hours ?? 1.0
            ];
        })->toArray();

        return $this->gradingSystemService->calculateGPA($gradeData);
    }

    /**
     * Get grade statistics for a class
     */
    public function getClassGradeStatistics(int $classId, ?string $assessmentName = null): array
    {
        $user = Auth::user();

        $query = GradeEntry::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('class_id', $classId);

        if ($assessmentName) {
            $query->where('assessment_name', $assessmentName);
        }

        $gradeEntries = $query->get();

        if ($gradeEntries->isEmpty()) {
            return [
                'count' => 0,
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'distribution' => []
            ];
        }

        $scores = $gradeEntries->pluck('percentage_score')->filter();

        return [
            'count' => $gradeEntries->count(),
            'average' => round($scores->avg() ?? 0, 2),
            'highest' => $scores->max() ?? 0,
            'lowest' => $scores->min() ?? 0,
            'distribution' => $gradeEntries->groupBy('letter_grade')
                ->map(function($group) { return $group->count(); })
                ->toArray()
        ];
    }

    /**
     * Calculate grade values (percentage, letter grade)
     */
    private function calculateGradeValues(array &$data, ?GradeEntry $existing = null): void
    {
        // Calculate percentage score if not provided
        if (!isset($data['percentage_score'])) {
            if (isset($data['points_earned'], $data['points_possible']) && $data['points_possible'] > 0) {
                $data['percentage_score'] = ($data['points_earned'] / $data['points_possible']) * 100;
            } elseif (isset($data['raw_score'])) {
                $data['percentage_score'] = $data['raw_score'];
            }
        }

        // Calculate letter grade if percentage is available
        if (isset($data['percentage_score']) && !isset($data['letter_grade'])) {
            $gradeLevel = $this->gradingSystemService->getGradeForPercentage($data['percentage_score']);
            if ($gradeLevel) {
                $data['letter_grade'] = $gradeLevel->grade_value;
            }
        }

        // Validate percentage range
        if (isset($data['percentage_score'])) {
            $data['percentage_score'] = max(0, min(100, $data['percentage_score']));
        }
    }
}

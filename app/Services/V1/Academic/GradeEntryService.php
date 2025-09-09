<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\SIS\Student\Student;
use App\Repositories\V1\Academic\GradeEntryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GradeEntryService extends BaseAcademicService
{
    protected GradeEntryRepository $gradeEntryRepository;
    protected GradingSystemService $gradingSystemService;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        GradeEntryRepository $gradeEntryRepository,
        GradingSystemService $gradingSystemService
    ) {
        parent::__construct($schoolContextService);
        $this->gradeEntryRepository = $gradeEntryRepository;
        $this->gradingSystemService = $gradingSystemService;
    }

    /**
     * Get paginated grade entries with filters
     */
    public function getGradeEntries(array $filters = []): LengthAwarePaginator
    {
        return $this->gradeEntryRepository->getWithFilters($filters);
    }

    /**
     * Create new grade entry
     */
    public function createGradeEntry(array $data)
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['entered_by'] = auth()->id();

        // Calculate derived values
        $this->calculateGradeValues($data);

        return $this->gradeEntryRepository->create($data);
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
                    $gradeData['school_id'] = $this->getCurrentSchoolId();
                    $gradeData['entered_by'] = auth()->id();
                    $gradeData['class_id'] = $data['class_id'];
                    $gradeData['academic_term_id'] = $data['academic_term_id'];
                    $gradeData['assessment_name'] = $data['assessment_name'];
                    $gradeData['assessment_type'] = $data['assessment_type'];
                    $gradeData['assessment_date'] = $data['assessment_date'];

                    $this->calculateGradeValues($gradeData);

                    $gradeEntry = $this->gradeEntryRepository->create($gradeData);
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

        $data['modified_by'] = auth()->id();

        // Recalculate derived values if score changed
        if (isset($data['raw_score']) || isset($data['points_earned']) || isset($data['percentage_score'])) {
            $this->calculateGradeValues($data, $gradeEntry);
        }

        return $this->gradeEntryRepository->update($gradeEntry, $data);
    }

    /**
     * Delete grade entry
     */
    public function deleteGradeEntry(GradeEntry $gradeEntry): bool
    {
        $this->validateSchoolOwnership($gradeEntry);

        return $this->gradeEntryRepository->delete($gradeEntry);
    }

    /**
     * Get student grades for a term
     */
    public function getStudentGrades(int $studentId, int $academicTermId): Collection
    {
        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        return $this->gradeEntryRepository->getStudentGrades($studentId, $academicTermId);
    }

    /**
     * Get class grades for an assessment
     */
    public function getClassGrades(int $classId, string $assessmentName): Collection
    {
        return $this->gradeEntryRepository->getClassGrades($classId, $assessmentName);
    }

    /**
     * Calculate student GPA for a term
     */
    public function calculateStudentGPA(int $studentId, int $academicTermId): float
    {
        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        $gradeEntries = $this->gradeEntryRepository->getStudentGradesForGPA($studentId, $academicTermId);

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
        return $this->gradeEntryRepository->getClassStatistics($classId, $assessmentName);
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

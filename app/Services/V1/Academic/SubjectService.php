<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\Subject;
use App\Repositories\V1\Academic\SubjectRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectService extends BaseAcademicService
{
    protected SubjectRepository $subjectRepository;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        SubjectRepository $subjectRepository
    ) {
        parent::__construct($schoolContextService);
        $this->subjectRepository = $subjectRepository;
    }

    /**
     * Get paginated subjects with filters
     */
    public function getSubjects(array $filters = []): LengthAwarePaginator
    {
        return $this->subjectRepository->getWithFilters($filters);
    }

    /**
     * Create new subject
     */
    public function createSubject(array $data): Subject
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        // Validate subject code uniqueness
        $this->validateSubjectCode($data['code']);

        // Validate grade levels
        $this->validateGradeLevels($data['grade_levels'] ?? []);

        // Set default credit hours based on subject area
        if (!isset($data['credit_hours'])) {
            $data['credit_hours'] = $this->getDefaultCreditHours($data['subject_area']);
        }

        return $this->subjectRepository->create($data);
    }

    /**
     * Update subject
     */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $this->validateSchoolOwnership($subject);

        // Validate subject code uniqueness if changed
        if (isset($data['code']) && $data['code'] !== $subject->code) {
            $this->validateSubjectCode($data['code']);
        }

        // Validate grade levels if changed
        if (isset($data['grade_levels'])) {
            $this->validateGradeLevels($data['grade_levels']);
        }

        return $this->subjectRepository->update($subject, $data);
    }

    /**
     * Archive subject (soft delete)
     */
    public function deleteSubject(Subject $subject): bool
    {
        $this->validateSchoolOwnership($subject);

        // Check for active classes
        if ($subject->classes()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot archive subject with active classes');
        }

        $this->subjectRepository->update($subject, ['status' => 'archived']);
        return true;
    }

    /**
     * Get subjects by grade level
     */
    public function getSubjectsByGradeLevel(string $gradeLevel): Collection
    {
        return $this->subjectRepository->getByGradeLevel($gradeLevel);
    }

    /**
     * Get core subjects
     */
    public function getCoreSubjects(): Collection
    {
        return $this->subjectRepository->getCoreSubjects();
    }

    /**
     * Get elective subjects
     */
    public function getElectiveSubjects(): Collection
    {
        return $this->subjectRepository->getElectiveSubjects();
    }

    /**
     * Get subjects by area
     */
    public function getSubjectsByArea(string $area): Collection
    {
        return $this->subjectRepository->getByArea($area);
    }

    /**
     * Get subject statistics
     */
    public function getSubjectStatistics(): array
    {
        return [
            'total' => $this->subjectRepository->count(),
            'core' => $this->subjectRepository->getCoreSubjects()->count(),
            'electives' => $this->subjectRepository->getElectiveSubjects()->count(),
            'by_area' => $this->subjectRepository->getStatsByArea(),
            'by_grade' => $this->subjectRepository->getStatsByGrade()
        ];
    }

    /**
     * Validate subject code uniqueness
     */
    private function validateSubjectCode(string $code): void
    {
        if ($this->subjectRepository->codeExists($code)) {
            throw new \Exception('Subject code already exists');
        }
    }

    /**
     * Validate grade levels
     */
    private function validateGradeLevels(array $gradeLevels): void
    {
        $validGrades = ['K', 'Pre-K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        foreach ($gradeLevels as $grade) {
            if (!in_array($grade, $validGrades)) {
                throw new \InvalidArgumentException("Invalid grade level: {$grade}");
            }
        }
    }

    /**
     * Get default credit hours based on subject area
     */
    private function getDefaultCreditHours(string $subjectArea): float
    {
        $defaultCredits = [
            'mathematics' => 1.0,
            'science' => 1.0,
            'language_arts' => 1.0,
            'social_studies' => 1.0,
            'foreign_language' => 1.0,
            'arts' => 0.5,
            'physical_education' => 0.5,
            'technology' => 0.5,
            'vocational' => 1.0,
            'other' => 0.5
        ];

        return $defaultCredits[$subjectArea] ?? 1.0;
    }
}

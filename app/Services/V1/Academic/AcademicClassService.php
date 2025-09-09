<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\SIS\Student\Student;
use App\Repositories\V1\Academic\AcademicClassRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AcademicClassService extends BaseAcademicService
{
    protected AcademicClassRepository $classRepository;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        AcademicClassRepository $classRepository
    ) {
        parent::__construct($schoolContextService);
        $this->classRepository = $classRepository;
    }

    /**
     * Get paginated classes with filters
     */
    public function getClasses(array $filters = []): LengthAwarePaginator
    {
        return $this->classRepository->getWithFilters($filters);
    }

    /**
     * Create new class
     */
    public function createClass(array $data): AcademicClass
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        // Validate class code uniqueness if provided
        if (isset($data['class_code'])) {
            $this->validateClassCode($data['class_code']);
        }

        // Validate teacher assignment
        if (isset($data['primary_teacher_id'])) {
            $this->validateTeacherAssignment($data['primary_teacher_id']);
        }

        // Validate schedule conflicts
        if (isset($data['schedule_json'])) {
            $this->validateScheduleConflicts($data);
        }

        return $this->classRepository->create($data);
    }

    /**
     * Update class
     */
    public function updateClass(AcademicClass $class, array $data): AcademicClass
    {
        $this->validateSchoolOwnership($class);

        // Validate class code if changed
        if (isset($data['class_code']) && $data['class_code'] !== $class->class_code) {
            $this->validateClassCode($data['class_code']);
        }

        // Validate teacher assignment if changed
        if (isset($data['primary_teacher_id']) && $data['primary_teacher_id'] !== $class->primary_teacher_id) {
            $this->validateTeacherAssignment($data['primary_teacher_id']);
        }

        return $this->classRepository->update($class, $data);
    }

    /**
     * Delete class
     */
    public function deleteClass(AcademicClass $class): bool
    {
        $this->validateSchoolOwnership($class);

        // Check for enrolled students
        if ($class->students()->wherePivot('status', 'active')->exists()) {
            throw new \Exception('Cannot delete class with enrolled students');
        }

        // Check for grade entries
        if ($class->gradeEntries()->exists()) {
            throw new \Exception('Cannot delete class with grade entries');
        }

        return $this->classRepository->delete($class);
    }

    /**
     * Enroll student in class
     */
    public function enrollStudent(AcademicClass $class, int $studentId): array
    {
        $this->validateSchoolOwnership($class);

        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        // Check class capacity
        if (!$class->hasAvailableSeats()) {
            throw new \Exception('Class is at maximum capacity');
        }

        // Check if student is already enrolled
        if ($class->students()->where('student_id', $studentId)->exists()) {
            throw new \Exception('Student is already enrolled in this class');
        }

        // Check grade level compatibility
        if ($student->current_grade_level !== $class->grade_level) {
            throw new \Exception('Student grade level does not match class grade level');
        }

        $class->students()->attach($studentId, [
            'enrollment_date' => now(),
            'status' => 'active'
        ]);

        $class->increment('current_enrollment');

        return [
            'student_id' => $studentId,
            'class_id' => $class->id,
            'enrollment_date' => now(),
            'status' => 'active'
        ];
    }

    /**
     * Remove student from class
     */
    public function removeStudent(AcademicClass $class, int $studentId): bool
    {
        $this->validateSchoolOwnership($class);

        $student = Student::findOrFail($studentId);
        $this->validateSchoolOwnership($student);

        if (!$class->students()->where('student_id', $studentId)->exists()) {
            throw new \Exception('Student is not enrolled in this class');
        }

        $class->students()->detach($studentId);
        $class->decrement('current_enrollment');

        return true;
    }

    /**
     * Get class roster
     */
    public function getClassRoster(AcademicClass $class): Collection
    {
        $this->validateSchoolOwnership($class);

        return $class->students()
            ->withPivot(['enrollment_date', 'status', 'final_grade'])
            ->wherePivot('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get teacher's classes
     */
    public function getTeacherClasses(int $teacherId, array $filters = []): Collection
    {
        return $this->classRepository->getByTeacher($teacherId, $filters);
    }

    /**
     * Get class statistics
     */
    public function getClassStatistics(AcademicClass $class): array
    {
        $this->validateSchoolOwnership($class);

        return [
            'enrollment' => [
                'current' => $class->current_enrollment,
                'capacity' => $class->max_students,
                'percentage' => $class->getEnrollmentPercentage(),
                'available_seats' => $class->getAvailableSeats()
            ],
            'grades' => $this->getClassGradeStatistics($class),
            'attendance' => $this->getClassAttendanceStatistics($class)
        ];
    }

    /**
     * Validate class code uniqueness
     */
    private function validateClassCode(string $classCode): void
    {
        if ($this->classRepository->codeExists($classCode)) {
            throw new \Exception('Class code already exists');
        }
    }

    /**
     * Validate teacher assignment
     */
    private function validateTeacherAssignment(int $teacherId): void
    {
        $teacher = \App\Models\User::find($teacherId);

        if (!$teacher || $teacher->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Invalid teacher assignment');
        }

        if (!in_array($teacher->user_type, ['teacher', 'admin', 'principal'])) {
            throw new \Exception('User is not authorized to teach classes');
        }
    }

    /**
     * Validate schedule conflicts
     */
    private function validateScheduleConflicts(array $data): void
    {
        // Implementation depends on schedule format
        // This would check for room and teacher conflicts
        // For now, we'll skip detailed implementation
    }

    /**
     * Get class grade statistics
     */
    private function getClassGradeStatistics(AcademicClass $class): array
    {
        $gradeEntries = $class->gradeEntries;

        if ($gradeEntries->isEmpty()) {
            return ['average' => null, 'count' => 0];
        }

        return [
            'average' => $gradeEntries->avg('percentage_score'),
            'count' => $gradeEntries->count(),
            'distribution' => $gradeEntries->groupBy('letter_grade')
                ->map(fn($grades) => $grades->count())
                ->toArray()
        ];
    }

    /**
     * Get class attendance statistics
     */
    private function getClassAttendanceStatistics(AcademicClass $class): array
    {
        // This would require attendance records
        // Placeholder implementation
        return [
            'average_rate' => null,
            'total_sessions' => 0
        ];
    }
}

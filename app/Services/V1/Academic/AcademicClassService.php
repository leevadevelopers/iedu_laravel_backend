<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class AcademicClassService extends BaseAcademicService
{
    public function __construct()
    {
        // No longer using repositories
    }

    /**
     * Get paginated classes with filters
     */
    public function getClasses(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = AcademicClass::where('tenant_id', $user->tenant_id)
            ->where('school_id', $filters['school_id'] ?? $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('class_code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['grade_level'])) {
            $query->where('grade_level', $filters['grade_level']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['primary_teacher_id'])) {
            $query->where('primary_teacher_id', $filters['primary_teacher_id']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }

        return $query->with(['subject', 'primaryTeacher', 'academicYear', 'academicTerm', 'school'])
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new class
     */
    public function createClass(array $data): AcademicClass
    {
        $user = Auth::user();

        // Add tenant_id from authenticated user
        $data['tenant_id'] = $user->tenant_id;

        // Validate class code uniqueness if provided
        if (isset($data['class_code'])) {
            $this->validateClassCode($data['class_code'], $data['school_id']);
        }

        // Validate teacher assignment
        if (isset($data['primary_teacher_id'])) {
            $this->validateTeacherAssignment($data['primary_teacher_id'], $data['school_id']);
        }

        // Validate schedule conflicts
        if (isset($data['schedule_json'])) {
            $this->validateScheduleConflicts($data);
        }

        return AcademicClass::create($data);
    }

    /**
     * Update class
     */
    public function updateClass(AcademicClass $class, array $data): AcademicClass
    {
        $this->validateTenantAndSchoolOwnership($class);

        // Validate class code if changed
        if (isset($data['class_code']) && $data['class_code'] !== $class->class_code) {
            $this->validateClassCode($data['class_code'], $data['school_id'] ?? $class->school_id);
        }

        // Validate teacher assignment if changed
        if (isset($data['primary_teacher_id']) && $data['primary_teacher_id'] !== $class->primary_teacher_id) {
            $this->validateTeacherAssignment($data['primary_teacher_id'], $data['school_id'] ?? $class->school_id);
        }

        $class->update($data);
        return $class->fresh();
    }

    /**
     * Delete class
     */
    public function deleteClass(AcademicClass $class): bool
    {
        $this->validateTenantAndSchoolOwnership($class);

        // Check for enrolled students
        if ($class->students()->wherePivot('status', 'active')->exists()) {
            throw new \Exception('Cannot delete class with enrolled students');
        }

        // Check for grade entries
        if ($class->gradeEntries()->exists()) {
            throw new \Exception('Cannot delete class with grade entries');
        }

        return $class->delete();
    }

    /**
     * Enroll student in class
     */
    public function enrollStudent(AcademicClass $class, int $studentId): array
    {
        $this->validateTenantAndSchoolOwnership($class);

        $student = Student::findOrFail($studentId);

        // Validate student belongs to same tenant and school
        if ($student->tenant_id !== $class->tenant_id) {
            throw new \Exception('Student does not belong to the same tenant');
        }

        if ($student->school_id !== $class->school_id) {
            throw new \Exception('Student does not belong to the same school');
        }

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
        $user = Auth::user();

        $query = AcademicClass::where('tenant_id', $user->tenant_id)
            ->where('school_id', $filters['school_id'] ?? $this->getCurrentSchoolId())
            ->where('primary_teacher_id', $teacherId);

        // Apply additional filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }

        return $query->with(['subject', 'academicYear', 'academicTerm', 'school'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get class by ID
     */
    public function getClassById(int $id): ?AcademicClass
    {
        $user = Auth::user();

        return AcademicClass::where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->first();
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
    private function validateClassCode(string $classCode, int $schoolId): void
    {
        $user = Auth::user();

        if (AcademicClass::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId)
            ->where('class_code', $classCode)
            ->exists()) {
            throw new \Exception('Class code already exists');
        }
    }

    /**
     * Validate teacher assignment
     */
    private function validateTeacherAssignment(int $teacherId, int $schoolId): void
    {
        $user = Auth::user();
        $teacher = \App\Models\V1\Academic\Teacher::find($teacherId);

        if (!$teacher || $teacher->tenant_id !== $user->tenant_id) {
            throw new \Exception('Invalid teacher assignment - tenant mismatch');
        }

        if ($teacher->school_id !== $schoolId) {
            throw new \Exception('Invalid teacher assignment - school mismatch');
        }

        if ($teacher->status !== 'active') {
            throw new \Exception('Teacher is not active');
        }
    }

    /**
     * Validate tenant and school ownership
     */
    private function validateTenantAndSchoolOwnership($model): void
    {
        $user = Auth::user();

        if ($model->tenant_id !== $user->tenant_id) {
            throw new \Exception('Access denied: Resource does not belong to current tenant');
        }

        if ($model->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Access denied: Resource does not belong to current school');
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

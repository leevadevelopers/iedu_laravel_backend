#!/bin/bash

# iEDU Academic Management - Services Generation
# Creates all Laravel services for the Academic Management module

echo "⚙️ Creating iEDU Academic Management Services..."

# Create Services directory if not exists
mkdir -p app/Services/V1/Academic

# Base Academic Service
cat > app/Services/V1/Academic/BaseAcademicService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Services\SchoolContextService;

abstract class BaseAcademicService
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get current school context
     */
    protected function getCurrentSchool()
    {
        return $this->schoolContextService->getCurrentSchool();
    }

    /**
     * Get current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return $this->getCurrentSchool()->id;
    }

    /**
     * Validate school ownership
     */
    protected function validateSchoolOwnership($model): void
    {
        if ($model->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }

    /**
     * Apply school scope to query
     */
    protected function applySchoolScope($query)
    {
        return $query->where('school_id', $this->getCurrentSchoolId());
    }
}
EOF

# Teacher Service
cat > app/Services/V1/Academic/TeacherService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\Teacher;
use App\Models\User;
use App\Repositories\V1\Academic\TeacherRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TeacherService extends BaseAcademicService
{
    protected TeacherRepository $teacherRepository;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        TeacherRepository $teacherRepository
    ) {
        parent::__construct($schoolContextService);
        $this->teacherRepository = $teacherRepository;
    }

    /**
     * Get paginated teachers with filters
     */
    public function getTeachers(array $filters = []): LengthAwarePaginator
    {
        return $this->teacherRepository->getWithFilters($filters);
    }

    /**
     * Create new teacher
     */
    public function createTeacher(array $data): Teacher
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        // Validate employee ID uniqueness
        $this->validateEmployeeId($data['employee_id']);

        // Validate email uniqueness
        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        // Create or find user account
        $user = $this->createOrFindUser($data);
        $data['user_id'] = $user->id;

        // Validate specializations
        if (isset($data['specializations_json'])) {
            $this->validateSpecializations($data['specializations_json']);
        }

        // Set default values
        $data = $this->setDefaultValues($data);

        return $this->teacherRepository->create($data);
    }

    /**
     * Update teacher
     */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        $this->validateSchoolOwnership($teacher);

        // Validate employee ID uniqueness if changed
        if (isset($data['employee_id']) && $data['employee_id'] !== $teacher->employee_id) {
            $this->validateEmployeeId($data['employee_id']);
        }

        // Validate email uniqueness if changed
        if (isset($data['email']) && $data['email'] !== $teacher->email) {
            $this->validateEmail($data['email']);
        }

        // Validate specializations if changed
        if (isset($data['specializations_json'])) {
            $this->validateSpecializations($data['specializations_json']);
        }

        return $this->teacherRepository->update($teacher, $data);
    }

    /**
     * Delete teacher (soft delete)
     */
    public function deleteTeacher(Teacher $teacher): bool
    {
        $this->validateSchoolOwnership($teacher);

        // Check for active classes
        if ($teacher->classes()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete teacher with active classes');
        }

        // Check for grade entries
        if ($teacher->gradeEntries()->exists()) {
            throw new \Exception('Cannot delete teacher with grade entries');
        }

        return $this->teacherRepository->update($teacher, ['status' => 'terminated']);
    }

    /**
     * Get teachers by department
     */
    public function getTeachersByDepartment(string $department): Collection
    {
        return $this->teacherRepository->getByDepartment($department);
    }

    /**
     * Get teachers by employment type
     */
    public function getTeachersByEmploymentType(string $employmentType): Collection
    {
        return $this->teacherRepository->getByEmploymentType($employmentType);
    }

    /**
     * Get teachers by specialization
     */
    public function getTeachersBySpecialization(string $specialization): Collection
    {
        return $this->teacherRepository->getBySpecialization($specialization);
    }

    /**
     * Get teachers by grade level
     */
    public function getTeachersByGradeLevel(string $gradeLevel): Collection
    {
        return $this->teacherRepository->getByGradeLevel($gradeLevel);
    }

    /**
     * Search teachers
     */
    public function searchTeachers(string $search): Collection
    {
        return $this->teacherRepository->search($search);
    }

    /**
     * Get teacher workload
     */
    public function getTeacherWorkload(Teacher $teacher): array
    {
        $this->validateSchoolOwnership($teacher);

        return $teacher->getWorkload();
    }

    /**
     * Get teacher's classes
     */
    public function getTeacherClasses(Teacher $teacher, array $filters = []): Collection
    {
        $this->validateSchoolOwnership($teacher);

        return $teacher->classes()
            ->with(['subject', 'academicYear', 'academicTerm'])
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['academic_year_id']), fn($q) => $q->where('academic_year_id', $filters['academic_year_id']))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get teacher statistics
     */
    public function getTeacherStatistics(Teacher $teacher): array
    {
        $this->validateSchoolOwnership($teacher);

        $classes = $teacher->getCurrentClasses();
        $totalStudents = $teacher->getTotalStudents();

        return [
            'basic_info' => [
                'name' => $teacher->display_name,
                'employee_id' => $teacher->employee_id,
                'department' => $teacher->department,
                'position' => $teacher->position,
                'employment_type' => $teacher->employment_type,
                'years_of_service' => $teacher->getYearsOfService()
            ],
            'workload' => [
                'total_classes' => $classes->count(),
                'total_students' => $totalStudents,
                'average_class_size' => $classes->count() > 0 ? round($totalStudents / $classes->count(), 2) : 0
            ],
            'specializations' => $teacher->specializations_json ?? [],
            'education' => $teacher->getEducationSummary(),
            'certifications' => $teacher->getCertifications()
        ];
    }

    /**
     * Update teacher schedule
     */
    public function updateTeacherSchedule(Teacher $teacher, array $schedule): Teacher
    {
        $this->validateSchoolOwnership($teacher);

        $this->validateSchedule($schedule);

        return $this->teacherRepository->update($teacher, ['schedule_json' => $schedule]);
    }

    /**
     * Check teacher availability
     */
    public function checkTeacherAvailability(Teacher $teacher, string $day, string $time): bool
    {
        $this->validateSchoolOwnership($teacher);

        return $teacher->isAvailableAt($day, $time);
    }

    /**
     * Get teachers available at specific time
     */
    public function getAvailableTeachers(string $day, string $time): Collection
    {
        return $this->teacherRepository->getAvailableAt($day, $time);
    }

    /**
     * Assign teacher to class
     */
    public function assignTeacherToClass(Teacher $teacher, int $classId): bool
    {
        $this->validateSchoolOwnership($teacher);

        $class = \App\Models\V1\Academic\AcademicClass::findOrFail($classId);
        $this->validateSchoolOwnership($class);

        // Check if teacher can teach the subject
        if (!$teacher->canTeachSubject($class->subject->name)) {
            throw new \Exception('Teacher does not have specialization for this subject');
        }

        // Check availability conflicts
        if ($class->schedule_json) {
            $this->checkScheduleConflicts($teacher, $class->schedule_json);
        }

        return $this->teacherRepository->update($class, ['primary_teacher_id' => $teacher->id]);
    }

    /**
     * Get teacher performance metrics
     */
    public function getTeacherPerformanceMetrics(Teacher $teacher, int $academicTermId): array
    {
        $this->validateSchoolOwnership($teacher);

        $classes = $teacher->classes()
            ->whereHas('academicTerm', fn($q) => $q->where('id', $academicTermId))
            ->get();

        $totalStudents = $classes->sum('current_enrollment');
        $gradeEntries = $teacher->gradeEntries()
            ->whereHas('academicTerm', fn($q) => $q->where('id', $academicTermId))
            ->get();

        return [
            'classes_taught' => $classes->count(),
            'total_students' => $totalStudents,
            'grades_entered' => $gradeEntries->count(),
            'average_grade' => $gradeEntries->avg('percentage_score'),
            'grade_distribution' => $gradeEntries->groupBy('letter_grade')
                ->map(fn($grades) => $grades->count())
                ->toArray()
        ];
    }

    /**
     * Create or find user account for teacher
     */
    private function createOrFindUser(array $data): User
    {
        $userData = [
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'identifier' => $data['email'] ?? $data['employee_id'],
            'type' => 'email',
            'phone' => $data['phone'] ?? null,
            'company' => $this->getCurrentSchool()->name,
            'job_title' => $data['position'] ?? 'Teacher',
            'bio' => $data['bio'] ?? null,
            'profile_photo_path' => $data['profile_photo_path'] ?? null
        ];

        // Check if user already exists
        $user = User::where('identifier', $userData['identifier'])->first();

        if ($user) {
            return $user;
        }

        // Create new user
        $userData['password'] = Hash::make('temp_password_' . $data['employee_id']);
        $userData['must_change'] = true;

        return User::create($userData);
    }

    /**
     * Validate employee ID uniqueness
     */
    private function validateEmployeeId(string $employeeId): void
    {
        if ($this->teacherRepository->employeeIdExists($employeeId)) {
            throw new \Exception('Employee ID already exists');
        }
    }

    /**
     * Validate email uniqueness
     */
    private function validateEmail(string $email): void
    {
        if ($this->teacherRepository->emailExists($email)) {
            throw new \Exception('Email already exists');
        }
    }

    /**
     * Validate specializations
     */
    private function validateSpecializations(array $specializations): void
    {
        $validSpecializations = [
            'mathematics', 'science', 'language_arts', 'social_studies',
            'foreign_language', 'arts', 'physical_education', 'technology',
            'vocational', 'K', 'Pre-K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'
        ];

        foreach ($specializations as $specialization) {
            if (!in_array($specialization, $validSpecializations)) {
                throw new \InvalidArgumentException("Invalid specialization: {$specialization}");
            }
        }
    }

    /**
     * Set default values for teacher
     */
    private function setDefaultValues(array $data): array
    {
        $defaults = [
            'status' => 'active',
            'employment_type' => 'full_time',
            'hire_date' => now()->toDateString(),
            'specializations_json' => [],
            'education_json' => [],
            'certifications_json' => [],
            'emergency_contacts_json' => [],
            'preferences_json' => []
        ];

        return array_merge($defaults, $data);
    }

    /**
     * Validate schedule format
     */
    private function validateSchedule(array $schedule): void
    {
        $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $validTimes = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

        foreach ($schedule as $day => $daySchedule) {
            if (!in_array(strtolower($day), $validDays)) {
                throw new \InvalidArgumentException("Invalid day: {$day}");
            }

            if (isset($daySchedule['available_times'])) {
                foreach ($daySchedule['available_times'] as $time) {
                    if (!in_array($time, $validTimes)) {
                        throw new \InvalidArgumentException("Invalid time: {$time}");
                    }
                }
            }
        }
    }

    /**
     * Check schedule conflicts
     */
    private function checkScheduleConflicts(Teacher $teacher, array $classSchedule): void
    {
        $teacherSchedule = $teacher->getTeachingSchedule();

        foreach ($classSchedule as $day => $daySchedule) {
            if (isset($teacherSchedule[$day])) {
                $teacherTimes = $teacherSchedule[$day]['available_times'] ?? [];
                $classTimes = $daySchedule['times'] ?? [];

                $conflicts = array_intersect($teacherTimes, $classTimes);
                if (!empty($conflicts)) {
                    throw new \Exception("Schedule conflict on {$day} at " . implode(', ', $conflicts));
                }
            }
        }
    }
}
EOF

# Subject Service
cat > app/Services/V1/Academic/SubjectService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Models\Academic\Subject;
use App\Repositories\Academic\SubjectRepository;
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

        return $this->subjectRepository->update($subject, ['status' => 'archived']);
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
EOF

# Academic Class Service
cat > app/Services/V1/Academic/AcademicClassService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Models\Academic\AcademicClass;
use App\Models\Student;
use App\Repositories\Academic\AcademicClassRepository;
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
EOF

# Grading System Service
cat > app/Services/V1/Academic/GradingSystemService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Models\Academic\GradingSystem;
use App\Models\Academic\GradeScale;
use App\Models\Academic\GradeLevel;
use App\Repositories\Academic\GradingSystemRepository;
use Illuminate\Database\Eloquent\Collection;

class GradingSystemService extends BaseAcademicService
{
    protected GradingSystemRepository $gradingSystemRepository;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        GradingSystemRepository $gradingSystemRepository
    ) {
        parent::__construct($schoolContextService);
        $this->gradingSystemRepository = $gradingSystemRepository;
    }

    /**
     * Get grading systems with filters
     */
    public function getGradingSystems(array $filters = []): Collection
    {
        return $this->gradingSystemRepository->getWithFilters($filters);
    }

    /**
     * Create new grading system
     */
    public function createGradingSystem(array $data): GradingSystem
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        $gradingSystem = $this->gradingSystemRepository->create($data);

        // Create default grade scale
        $this->createDefaultGradeScale($gradingSystem);

        return $gradingSystem->load('gradeScales.gradeLevels');
    }

    /**
     * Update grading system
     */
    public function updateGradingSystem(GradingSystem $gradingSystem, array $data): GradingSystem
    {
        $this->validateSchoolOwnership($gradingSystem);

        return $this->gradingSystemRepository->update($gradingSystem, $data);
    }

    /**
     * Delete grading system
     */
    public function deleteGradingSystem(GradingSystem $gradingSystem): bool
    {
        $this->validateSchoolOwnership($gradingSystem);

        // Check if it's the primary system
        if ($gradingSystem->is_primary) {
            throw new \Exception('Cannot delete primary grading system');
        }

        // Check for dependencies (grade entries using this system)
        if ($this->hasGradingSystemDependencies($gradingSystem)) {
            throw new \Exception('Cannot delete grading system with existing grade entries');
        }

        return $this->gradingSystemRepository->delete($gradingSystem);
    }

    /**
     * Get primary grading system
     */
    public function getPrimaryGradingSystem(): ?GradingSystem
    {
        return $this->gradingSystemRepository->getPrimary();
    }

    /**
     * Set grading system as primary
     */
    public function setPrimaryGradingSystem(GradingSystem $gradingSystem): GradingSystem
    {
        $this->validateSchoolOwnership($gradingSystem);

        // Remove primary flag from other systems
        $this->gradingSystemRepository->clearPrimaryFlags();

        return $this->gradingSystemRepository->update($gradingSystem, ['is_primary' => true]);
    }

    /**
     * Create grade scale for grading system
     */
    public function createGradeScale(GradingSystem $gradingSystem, array $data): GradeScale
    {
        $this->validateSchoolOwnership($gradingSystem);

        $data['grading_system_id'] = $gradingSystem->id;
        $data['school_id'] = $this->getCurrentSchoolId();

        $gradeScale = GradeScale::create($data);

        // Create default grade levels
        $this->createDefaultGradeLevels($gradeScale);

        return $gradeScale->load('gradeLevels');
    }

    /**
     * Get grade for percentage
     */
    public function getGradeForPercentage(float $percentage, ?int $gradeScaleId = null): ?GradeLevel
    {
        if (!$gradeScaleId) {
            $primarySystem = $this->getPrimaryGradingSystem();
            if (!$primarySystem || !$primarySystem->gradeScales->isNotEmpty()) {
                return null;
            }
            $gradeScale = $primarySystem->gradeScales->where('is_default', true)->first()
                        ?? $primarySystem->gradeScales->first();
        } else {
            $gradeScale = GradeScale::find($gradeScaleId);
        }

        if (!$gradeScale) {
            return null;
        }

        return $gradeScale->getGradeForPercentage($percentage);
    }

    /**
     * Calculate GPA for grades
     */
    public function calculateGPA(array $gradeEntries, ?int $gradeScaleId = null): float
    {
        if (empty($gradeEntries)) {
            return 0.0;
        }

        $totalPoints = 0;
        $totalCredits = 0;

        foreach ($gradeEntries as $entry) {
            $gradeLevel = $this->getGradeForPercentage($entry['percentage'], $gradeScaleId);
            if ($gradeLevel && $gradeLevel->gpa_points !== null) {
                $credits = $entry['credits'] ?? 1.0;
                $totalPoints += $gradeLevel->gpa_points * $credits;
                $totalCredits += $credits;
            }
        }

        return $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.0;
    }

    /**
     * Create default grade scale for grading system
     */
    private function createDefaultGradeScale(GradingSystem $gradingSystem): void
    {
        $scaleName = match ($gradingSystem->system_type) {
            'traditional_letter' => 'Standard Letter Grades',
            'percentage' => 'Percentage Scale',
            'points' => 'Points Scale',
            'standards_based' => 'Standards-Based Scale',
            'narrative' => 'Narrative Assessment',
            default => 'Default Scale'
        };

        $gradeScale = GradeScale::create([
            'grading_system_id' => $gradingSystem->id,
            'school_id' => $gradingSystem->school_id,
            'name' => $scaleName,
            'scale_type' => $gradingSystem->system_type === 'traditional_letter' ? 'letter' : $gradingSystem->system_type,
            'is_default' => true
        ]);

        $this->createDefaultGradeLevels($gradeScale);
    }

    /**
     * Create default grade levels for grade scale
     */
    private function createDefaultGradeLevels(GradeScale $gradeScale): void
    {
        $gradeLevels = match ($gradeScale->scale_type) {
            'letter' => $this->getTraditionalLetterGrades(),
            'percentage' => $this->getPercentageGrades(),
            'standards' => $this->getStandardsBasedGrades(),
            default => $this->getTraditionalLetterGrades()
        };

        foreach ($gradeLevels as $index => $level) {
            GradeLevel::create([
                'grade_scale_id' => $gradeScale->id,
                'grade_value' => $level['value'],
                'display_value' => $level['display'],
                'numeric_value' => $level['numeric'],
                'gpa_points' => $level['gpa'] ?? null,
                'percentage_min' => $level['min_percent'] ?? null,
                'percentage_max' => $level['max_percent'] ?? null,
                'description' => $level['description'] ?? null,
                'color_code' => $level['color'] ?? null,
                'is_passing' => $level['passing'] ?? true,
                'sort_order' => $index + 1
            ]);
        }
    }

    /**
     * Get traditional letter grade definitions
     */
    private function getTraditionalLetterGrades(): array
    {
        return [
            ['value' => 'A+', 'display' => 'A+', 'numeric' => 97.0, 'gpa' => 4.0, 'min_percent' => 97.0, 'max_percent' => 100.0, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'A', 'display' => 'A', 'numeric' => 95.0, 'gpa' => 4.0, 'min_percent' => 93.0, 'max_percent' => 96.9, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'A-', 'display' => 'A-', 'numeric' => 92.0, 'gpa' => 3.7, 'min_percent' => 90.0, 'max_percent' => 92.9, 'color' => '#2ECC40', 'passing' => true],
            ['value' => 'B+', 'display' => 'B+', 'numeric' => 89.0, 'gpa' => 3.3, 'min_percent' => 87.0, 'max_percent' => 89.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'B', 'display' => 'B', 'numeric' => 85.0, 'gpa' => 3.0, 'min_percent' => 83.0, 'max_percent' => 86.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'B-', 'display' => 'B-', 'numeric' => 82.0, 'gpa' => 2.7, 'min_percent' => 80.0, 'max_percent' => 82.9, 'color' => '#01FF70', 'passing' => true],
            ['value' => 'C+', 'display' => 'C+', 'numeric' => 79.0, 'gpa' => 2.3, 'min_percent' => 77.0, 'max_percent' => 79.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'C', 'display' => 'C', 'numeric' => 75.0, 'gpa' => 2.0, 'min_percent' => 73.0, 'max_percent' => 76.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'C-', 'display' => 'C-', 'numeric' => 72.0, 'gpa' => 1.7, 'min_percent' => 70.0, 'max_percent' => 72.9, 'color' => '#FFDC00', 'passing' => true],
            ['value' => 'D+', 'display' => 'D+', 'numeric' => 69.0, 'gpa' => 1.3, 'min_percent' => 67.0, 'max_percent' => 69.9, 'color' => '#FF851B', 'passing' => true],
            ['value' => 'D', 'display' => 'D', 'numeric' => 65.0, 'gpa' => 1.0, 'min_percent' => 60.0, 'max_percent' => 66.9, 'color' => '#FF851B', 'passing' => true],
            ['value' => 'F', 'display' => 'F', 'numeric' => 0.0, 'gpa' => 0.0, 'min_percent' => 0.0, 'max_percent' => 59.9, 'color' => '#FF4136', 'passing' => false]
        ];
    }

    /**
     * Get percentage grade definitions
     */
    private function getPercentageGrades(): array
    {
        $grades = [];
        for ($i = 100; $i >= 0; $i -= 10) {
            $max = min($i + 9, 100);
            $grades[] = [
                'value' => (string)$i,
                'display' => "{$i}-{$max}%",
                'numeric' => (float)$i,
                'min_percent' => (float)$i,
                'max_percent' => (float)$max,
                'passing' => $i >= 60
            ];
        }
        return $grades;
    }

    /**
     * Get standards-based grade definitions
     */
    private function getStandardsBasedGrades(): array
    {
        return [
            ['value' => '4', 'display' => 'Exceeds Standards', 'numeric' => 4.0, 'gpa' => 4.0, 'color' => '#2ECC40', 'passing' => true],
            ['value' => '3', 'display' => 'Meets Standards', 'numeric' => 3.0, 'gpa' => 3.0, 'color' => '#01FF70', 'passing' => true],
            ['value' => '2', 'display' => 'Approaching Standards', 'numeric' => 2.0, 'gpa' => 2.0, 'color' => '#FFDC00', 'passing' => true],
            ['value' => '1', 'display' => 'Below Standards', 'numeric' => 1.0, 'gpa' => 1.0, 'color' => '#FF4136', 'passing' => false]
        ];
    }

    /**
     * Check if grading system has dependencies
     */
    private function hasGradingSystemDependencies(GradingSystem $gradingSystem): bool
    {
        // Check for grade entries using this system's scales
        foreach ($gradingSystem->gradeScales as $scale) {
            foreach ($scale->gradeLevels as $level) {
                if (\App\Models\Academic\GradeEntry::where('letter_grade', $level->grade_value)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
EOF

# Grade Entry Service
cat > app/Services/V1/Academic/GradeEntryService.php << 'EOF'
<?php

namespace App\Services\V1\Academic;

use App\Models\Academic\GradeEntry;
use App\Models\Student;
use App\Repositories\Academic\GradeEntryRepository;
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
    public function createGradeEntry(array $data): GradeEntry
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
    public function updateGradeEntry(GradeEntry $gradeEntry, array $data): GradeEntry
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
EOF

echo "✅ Academic Management Services created successfully!"
echo "📁 Services created in: app/Services/V1/Academic/"
echo "📋 Created services:"
echo "   - BaseAcademicService"
echo "   - TeacherService"
echo "   - SubjectService"
echo "   - AcademicClassService"
echo "   - GradingSystemService"
echo "   - GradeEntryService"
echo "🔧 Next: Create Repositories and Request classes"

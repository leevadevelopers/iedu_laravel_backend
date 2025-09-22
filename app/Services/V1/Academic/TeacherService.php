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

    public function __construct(TeacherRepository $teacherRepository)
    {
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

        $this->teacherRepository->update($teacher, ['status' => 'terminated']);
        return true;
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

        $class->update(['primary_teacher_id' => $teacher->id]);
        return true;
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

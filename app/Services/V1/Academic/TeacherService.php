<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeacherService extends BaseAcademicService
{
    public function __construct()
    {
        // No longer using repositories
    }

    /**
     * Get paginated teachers with filters
     */
    public function getTeachers(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $filters['school_id'] ?? $this->getCurrentSchoolId());

        // Apply filters
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('employee_id', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (isset($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['specialization'])) {
            $query->whereJsonContains('specializations_json', $filters['specialization']);
        }

        if (isset($filters['grade_level'])) {
            $query->whereJsonContains('specializations_json', $filters['grade_level']);
        }

        return $query->with(['user', 'school'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create new teacher
     */
    public function createTeacher(array $data): Teacher
    {
        $data['school_id'] = $this->getCurrentSchoolId();

        // Validate email uniqueness
        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        // Create or find user account
        $user = $this->createOrFindUser($data);
        $data['user_id'] = $user->id;

        // Specializations are now free-form, no validation needed

        // Set default values
        $data = $this->setDefaultValues($data);

        $user = Auth::user();
        $data['tenant_id'] = $user->tenant_id;

        return Teacher::create($data);
    }

    /**
     * Get teacher by ID
     */
    public function getTeacherById(int $id): ?Teacher
    {
        return Teacher::find($id);
    }

    /**
     * Update teacher
     */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        $this->validateSchoolOwnership($teacher);

        // Validate email uniqueness if changed
        if (isset($data['email']) && $data['email'] !== $teacher->email) {
            $this->validateEmail($data['email']);
        }

        // Specializations are now free-form, no validation needed

        $teacher->update($data);
        return $teacher->fresh();
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

        $teacher->update(['status' => 'terminated']);
        return true;
    }

    /**
     * Get teachers by department
     */
    public function getTeachersByDepartment(string $department, int $schoolId = null): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->where('department', $department)
            ->with(['user', 'school'])
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Get teachers by employment type
     */
    public function getTeachersByEmploymentType(string $employmentType, int $schoolId = null): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->where('employment_type', $employmentType)
            ->with(['user', 'school'])
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Get teachers by specialization
     */
    public function getTeachersBySpecialization(string $specialization, int $schoolId = null): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->whereJsonContains('specializations_json', $specialization)
            ->with(['user', 'school'])
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Get teachers by grade level
     */
    public function getTeachersByGradeLevel(string $gradeLevel, int $schoolId = null): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->whereJsonContains('specializations_json', $gradeLevel)
            ->with(['user', 'school'])
            ->orderBy('last_name')
            ->paginate(15);
    }

    /**
     * Search teachers
     */
    public function searchTeachers(string $search, int $schoolId = null): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId ?? $this->getCurrentSchoolId())
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('employee_id', 'like', '%' . $search . '%');
            })
            ->with(['user', 'school'])
            ->orderBy('last_name')
            ->paginate(15);
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

        $teacher->update(['schedule_json' => $schedule]);
        return $teacher->fresh();
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
    public function getAvailableTeachers(string $day, string $time): LengthAwarePaginator
    {
        $user = Auth::user();

        return Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('status', 'active')
            ->where(function ($q) use ($day, $time) {
                $q->whereNull('schedule_json')
                  ->orWhereJsonDoesntContain('schedule_json', [
                      'day' => $day,
                      'start_time' => '<=',
                      'end_time' => '>='
                  ]);
            })
            ->with(['user', 'school'])
            ->paginate(15);
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
            'profile_photo_path' => $data['profile_photo_path'] ?? null,
            'user_type' => 'teacher'
        ];

        // Check if user already exists
        $user = User::where('identifier', $userData['identifier'])->first();

        if ($user) {
            return $user;
        }

        // Create new user
        $employeeId = $data['employee_id'] ?? 'TEMP_' . uniqid();
        $userData['password'] = Hash::make('temp_password_' . $employeeId);
        $userData['must_change'] = true;

        return User::create($userData);
    }


    /**
     * Validate email uniqueness
     */
    private function validateEmail(string $email): void
    {
        $user = Auth::user();

        if (Teacher::where('tenant_id', $user->tenant_id)
            ->where('school_id', $this->getCurrentSchoolId())
            ->where('email', $email)
            ->exists()) {
            throw new \Exception('Email already exists');
        }
    }

    /**
     * Get valid specializations
     */
    public function getValidSpecializations(): array
    {
        return [
            'academic_subjects' => [
                'mathematics', 'science', 'language_arts', 'social_studies',
                'foreign_language', 'arts', 'physical_education', 'technology',
                'vocational', 'history', 'geography', 'biology', 'chemistry', 'physics',
                'literature', 'writing', 'reading', 'grammar', 'spelling'
            ],
            'administrative_support' => [
                'office_management', 'administration', 'secretarial', 'clerical',
                'student_services', 'counseling', 'nursing', 'librarian',
                'technology_support', 'maintenance', 'security', 'transportation',
                'cafeteria', 'finance', 'human_resources', 'communications'
            ],
            'grade_levels' => [
                'K', 'Pre-K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'
            ],
            'special_education' => [
                'special_education', 'learning_support', 'gifted_education',
                'speech_therapy', 'occupational_therapy', 'physical_therapy'
            ],
            'extracurricular' => [
                'sports', 'music', 'drama', 'debate', 'journalism', 'yearbook',
                'student_government', 'clubs', 'community_service'
            ]
        ];
    }

    /**
     * Get flat list of all valid specializations
     */
    public function getAllValidSpecializations(): array
    {
        $specializations = $this->getValidSpecializations();
        $flatList = [];

        foreach ($specializations as $category => $items) {
            $flatList = array_merge($flatList, $items);
        }

        return $flatList;
    }

    /**
     * Validate specializations - DEPRECATED: Specializations are now free-form
     * @deprecated This method is no longer used as specializations are now free-form
     */
    private function validateSpecializations(array $specializations): void
    {
        // Specializations are now free-form, no validation needed
        // This method is kept for backward compatibility but does nothing
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

        foreach ($schedule as $scheduleItem) {
            // Check if this is the new format (array of objects with 'day' property)
            if (isset($scheduleItem['day'])) {
                $day = $scheduleItem['day'];
                if (!in_array(strtolower($day), $validDays)) {
                    throw new \InvalidArgumentException("Invalid day: {$day}");
                }

                if (isset($scheduleItem['available_times'])) {
                    foreach ($scheduleItem['available_times'] as $time) {
                        // Validate time format (HH:MM or HH:MM-HH:MM)
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](-[01]?[0-9]|2[0-3]:[0-5][0-9])?$/', $time)) {
                            throw new \InvalidArgumentException("Invalid time format: {$time}");
                        }
                    }
                }
            } else {
                // Legacy format (keyed by day name)
                foreach ($schedule as $day => $daySchedule) {
                    if (!in_array(strtolower($day), $validDays)) {
                        throw new \InvalidArgumentException("Invalid day: {$day}");
                    }

                    if (isset($daySchedule['available_times'])) {
                        foreach ($daySchedule['available_times'] as $time) {
                            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](-[01]?[0-9]|2[0-3]:[0-5][0-9])?$/', $time)) {
                                throw new \InvalidArgumentException("Invalid time format: {$time}");
                            }
                        }
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

<?php

namespace App\Services;

use App\Models\SchoolEntities\Student;
use App\Models\SchoolEntities\SchoolClass;
use App\Models\SchoolEntities\StudentParent;
use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchoolManagementService
{
    /**
     * Enroll a new student
     */
    public function enrollStudent(array $studentData, array $parentData = []): Student
    {
        DB::beginTransaction();

        try {
                    // Create or find parent
        $parent = null;
        if (!empty($parentData)) {
            $parent = $this->createOrFindParent($parentData);
        }

        // Create student
        $student = Student::create([
            'tenant_id' => auth()->user()?->current_tenant_id ?? 1,
            'student_code' => $this->generateStudentCode(),
            'first_name' => $studentData['first_name'],
            'last_name' => $studentData['last_name'],
            'email' => $studentData['email'] ?? null,
            'phone' => $studentData['phone'] ?? null,
            'date_of_birth' => $studentData['date_of_birth'] ?? null,
            'gender' => $studentData['gender'] ?? null,
            'address' => $studentData['address'] ?? null,
            'city' => $studentData['city'] ?? null,
            'state' => $studentData['state'] ?? null,
            'postal_code' => $studentData['postal_code'] ?? null,
            'country' => $studentData['country'] ?? null,
            'emergency_contact_name' => $studentData['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $studentData['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $studentData['emergency_contact_relationship'] ?? null,
            'enrollment_date' => $studentData['enrollment_date'] ?? now(),
            'grade_level' => $studentData['grade_level'],
            'class_id' => $studentData['class_id'] ?? null,
            'parent_id' => $parent?->id,
            'academic_year' => $studentData['academic_year'] ?? $this->getCurrentAcademicYear(),
            'status' => 'active',
            'created_by' => auth()->user()?->id ?? 1
        ]);

            // Assign to class if specified
            if (!empty($studentData['class_id'])) {
                $this->assignStudentToClass($student, $studentData['class_id']);
            }

            // Create enrollment form instance
            $this->createEnrollmentFormInstance($student);

            DB::commit();
            return $student;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to enroll student: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create or find existing parent
     */
    private function createOrFindParent(array $parentData): StudentParent
    {
        $existingParent = StudentParent::where('tenant_id', auth()->user()?->current_tenant_id ?? 1)
            ->where('email', $parentData['email'])
            ->first();

        if ($existingParent) {
            return $existingParent;
        }

        return StudentParent::create([
            'tenant_id' => auth()->user()?->current_tenant_id ?? 1,
            'first_name' => $parentData['first_name'],
            'last_name' => $parentData['last_name'],
            'email' => $parentData['email'] ?? null,
            'phone' => $parentData['phone'] ?? null,
            'address' => $parentData['address'] ?? null,
            'city' => $parentData['city'] ?? null,
            'state' => $parentData['state'] ?? null,
            'postal_code' => $parentData['postal_code'] ?? null,
            'country' => $parentData['country'] ?? null,
            'occupation' => $parentData['occupation'] ?? null,
            'employer' => $parentData['employer'] ?? null,
            'emergency_contact' => $parentData['emergency_contact'] ?? null,
            'relationship_type' => $parentData['relationship_type'] ?? 'other',
            'is_primary_contact' => $parentData['is_primary_contact'] ?? true,
            'can_pickup' => $parentData['can_pickup'] ?? false,
            'communication_preferences' => $parentData['communication_preferences'] ?? [],
            'created_by' => auth()->user()?->id ?? 1
        ]);
    }

    /**
     * Assign student to a class
     */
    public function assignStudentToClass(Student $student, int $classId): bool
    {
        $class = SchoolClass::findOrFail($classId);

        if (!$class->canEnrollStudent()) {
            throw new \Exception('Class is full or inactive');
        }

        // Remove from previous class if any
        if ($student->class_id && $student->class_id !== $classId) {
            $this->removeStudentFromClass($student);
        }

        // Assign to new class
        $student->update(['class_id' => $classId]);
        $class->increment('current_enrollment');

        return true;
    }

    /**
     * Remove student from class
     */
    public function removeStudentFromClass(Student $student): bool
    {
        if ($student->class_id) {
            $class = SchoolClass::find($student->class_id);
            if ($class) {
                $class->decrement('current_enrollment');
            }
            $student->update(['class_id' => null]);
        }

        return true;
    }

    /**
     * Create a new class
     */
    public function createClass(array $classData): SchoolClass
    {
        return SchoolClass::create([
            'tenant_id' => auth()->user()?->current_tenant_id ?? 1,
            'class_name' => $classData['class_name'],
            'class_code' => $this->generateClassCode($classData['grade_level']),
            'grade_level' => $classData['grade_level'],
            'academic_year' => $classData['academic_year'] ?? $this->getCurrentAcademicYear(),
            'teacher_id' => $classData['teacher_id'],
            'room_number' => $classData['room_number'] ?? null,
            'capacity' => $classData['capacity'] ?? 30,
            'current_enrollment' => 0,
            'schedule' => $classData['schedule'] ?? [],
            'subjects' => $classData['subjects'] ?? [],
            'description' => $classData['description'] ?? null,
            'is_active' => true,
            'created_by' => auth()->user()?->id ?? 1
        ]);
    }

    /**
     * Generate unique student code
     */
    private function generateStudentCode(): string
    {
        do {
            $code = 'STU-' . now()->format('y') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (Student::where('student_code', $code)->exists());

        return $code;
    }

    /**
     * Generate unique class code
     */
    private function generateClassCode(string $gradeLevel): string
    {
        do {
            $code = 'CLS-' . strtoupper($gradeLevel) . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
        } while (SchoolClass::where('class_code', $code)->exists());

        return $code;
    }

    /**
     * Get current academic year
     */
    private function getCurrentAcademicYear(): string
    {
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;
        return "{$currentYear}-{$nextYear}";
    }

    /**
     * Create enrollment form instance for student
     */
    private function createEnrollmentFormInstance(Student $student): FormInstance
    {
        $enrollmentTemplate = FormTemplate::where('tenant_id', $student->tenant_id)
            ->where('category', 'student_enrollment')
            ->where('is_active', true)
            ->first();

        if (!$enrollmentTemplate) {
            throw new \Exception('Enrollment form template not found');
        }

        return FormInstance::create([
            'tenant_id' => $student->tenant_id,
            'form_template_id' => $enrollmentTemplate->id,
            'user_id' => auth()->user()?->id,
            'reference_type' => 'student',
            'reference_id' => $student->id,
            'form_type' => 'enrollment',
            'form_data' => [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'enrollment_date' => $student->enrollment_date->format('Y-m-d'),
                'grade_level' => $student->grade_level,
                'academic_year' => $student->academic_year
            ],
            'status' => 'submitted',
            'submitted_at' => now(),
            'created_by' => auth()->user()?->id
        ]);
    }

    /**
     * Get students by class
     */
    public function getStudentsByClass(int $classId): \Illuminate\Database\Eloquent\Collection
    {
        return Student::where('class_id', $classId)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Get class statistics
     */
    public function getClassStatistics(int $classId): array
    {
        $class = SchoolClass::findOrFail($classId);
        $students = $this->getStudentsByClass($classId);

        return [
            'class_name' => $class->class_name,
            'grade_level' => $class->grade_level,
            'capacity' => $class->capacity,
            'current_enrollment' => $class->current_enrollment,
            'available_spots' => $class->available_spots,
            'enrollment_percentage' => $class->capacity > 0 ? round(($class->current_enrollment / $class->capacity) * 100, 2) : 0,
            'students' => $students->count(),
            'male_students' => $students->where('gender', 'male')->count(),
            'female_students' => $students->where('gender', 'female')->count(),
            'other_gender' => $students->whereNotIn('gender', ['male', 'female'])->count()
        ];
    }

    /**
     * Get student academic summary
     */
    public function getStudentAcademicSummary(int $studentId): array
    {
        $student = Student::findOrFail($studentId);
        $formInstances = $student->formInstances()
            ->with('template')
            ->whereIn('status', ['submitted', 'approved', 'completed'])
            ->get();

        $summary = [
            'student_info' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'grade_level' => $student->grade_level,
                'class' => $student->class ? $student->class->class_name : null,
                'enrollment_date' => $student->enrollment_date->format('Y-m-d'),
                'status' => $student->status
            ],
            'forms_submitted' => $formInstances->count(),
            'forms_by_category' => $formInstances->groupBy('template.category')->map->count(),
            'recent_activity' => $formInstances->sortByDesc('created_at')->take(5)->map(function ($instance) {
                return [
                    'form_name' => $instance->template->name,
                    'category' => $instance->template->category,
                    'status' => $instance->status,
                    'submitted_at' => $instance->submitted_at?->format('Y-m-d H:i:s')
                ];
            })
        ];

        return $summary;
    }
}

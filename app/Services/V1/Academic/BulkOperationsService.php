<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\School\SchoolUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Auth;

class BulkOperationsService extends BaseAcademicService
{
    protected AcademicClassService $classService;
    protected TeacherService $teacherService;
    protected SubjectService $subjectService;

    public function __construct(
        AcademicClassService $classService,
        TeacherService $teacherService,
        SubjectService $subjectService
    ) {
        $this->classService = $classService;
        $this->teacherService = $teacherService;
        $this->subjectService = $subjectService;
    }
    /**
     * Create multiple classes in bulk
     */
    public function createClasses(array $data): array
    {
        $classes = $data['classes'];
        $createdClasses = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($classes as $index => $classData) {
                try {
                    // Ensure school_id is set
                    if (!isset($classData['school_id'])) {
                        $classData['school_id'] = $this->getCurrentSchoolId();
                    }

                    // Use AcademicClassService to create class with all validations
                    $class = $this->classService->createClass($classData);
                    $createdClasses[] = [
                        'id' => $class->id,
                        'name' => $class->name,
                        'class_code' => $class->class_code,
                        'grade_level' => $class->grade_level
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $classData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($createdClasses)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'created_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'created_count' => count($createdClasses),
                'failed_count' => count($errors),
                'classes' => $createdClasses,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Enroll multiple students in bulk
     */
    public function enrollStudents(array $data): array
    {
        $enrollments = $data['enrollments'];
        $enrolledStudents = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($enrollments as $index => $enrollmentData) {
                try {
                    $class = $this->classService->getClassById($enrollmentData['class_id']);

                    if (!$class) {
                        throw new \Exception('Class not found');
                    }

                    // Use AcademicClassService to enroll student with all validations
                    $enrollment = $this->classService->enrollStudent($class, $enrollmentData['student_id']);

                    $student = Student::findOrFail($enrollmentData['student_id']);

                    $enrolledStudents[] = [
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'student_name' => $student->first_name . ' ' . $student->last_name,
                        'class_name' => $class->name,
                        'enrollment_date' => $enrollment['enrollment_date'] ?? now(),
                        'status' => $enrollment['status'] ?? 'active'
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $enrollmentData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($enrolledStudents)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'enrolled_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'enrolled_count' => count($enrolledStudents),
                'failed_count' => count($errors),
                'enrollments' => $enrolledStudents,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Import grades in bulk
     */
    public function importGrades(array $data): array
    {
        $grades = $data['grades'];
        $importedGrades = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($grades as $index => $gradeData) {
                try {
                    // Validate required relationships
                    $class = AcademicClass::findOrFail($gradeData['academic_class_id']);
                    $this->validateSchoolOwnership($class);

                    $student = Student::findOrFail($gradeData['student_id']);
                    $this->validateSchoolOwnership($student);

                    $subject = Subject::findOrFail($gradeData['subject_id']);
                    $this->validateSchoolOwnership($subject);

                    $gradeData['school_id'] = $this->getCurrentSchoolId();
                    $gradeData['entered_by'] = Auth::id();

                    $gradeEntry = GradeEntry::create($gradeData);
                    $importedGrades[] = $gradeEntry;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $gradeData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'imported_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'imported_count' => count($importedGrades),
                'grades' => $importedGrades
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate report cards in bulk
     */
    public function generateReportCards(array $data): array
    {
        $studentIds = $data['student_ids'];
        $academicYearId = $data['academic_year_id'] ?? null;
        $term = $data['term'] ?? null;

        $generatedReports = [];
        $errors = [];

        try {
            foreach ($studentIds as $studentId) {
                try {
                    $student = Student::findOrFail($studentId);
                    $this->validateSchoolOwnership($student);

                    // Generate report card (this would integrate with report card generation system)
                    $reportCard = $this->generateStudentReportCard($student, $academicYearId, $term);
                    $generatedReports[] = $reportCard;
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'success' => true,
                'generated_count' => count($generatedReports),
                'reports' => $generatedReports,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Update multiple students in bulk
     */
    public function updateStudents(array $data): array
    {
        $students = $data['students'];
        $updateType = $data['update_type'];
        $updatedStudents = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($students as $index => $studentData) {
                try {
                    $student = Student::findOrFail($studentData['id']);
                    $this->validateSchoolOwnership($student);

                    $updateData = $this->filterUpdateData($studentData['data'], $updateType);
                    $student->update($updateData);

                    $updatedStudents[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'updated_fields' => array_keys($updateData)
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'student_id' => $studentData['id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'updated_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'updated_count' => count($updatedStudents),
                'students' => $updatedStudents
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create multiple teachers in bulk
     */
    public function createTeachers(array $data): array
    {
        $teachers = $data['teachers'];
        $createdTeachers = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($teachers as $index => $teacherData) {
                try {
                    // Ensure school_id is set
                    if (!isset($teacherData['school_id'])) {
                        $teacherData['school_id'] = $this->getCurrentSchoolId();
                    }

                    // Use TeacherService to create teacher with all validations and user creation
                    $teacher = $this->teacherService->createTeacher($teacherData);

                    // Create SchoolUser association (same as TeacherController)
                    if ($teacher->user_id && $teacher->school_id) {
                        SchoolUser::create([
                            'school_id' => $teacher->school_id,
                            'user_id' => $teacher->user_id,
                            'role' => 'teacher',
                            'status' => 'active',
                            'start_date' => now(),
                            'permissions' => $this->getDefaultTeacherPermissions()
                        ]);
                    }

                    $createdTeachers[] = [
                        'id' => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name' => $teacher->last_name,
                        'employee_id' => $teacher->employee_id,
                        'email' => $teacher->email
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $teacherData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($createdTeachers)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'created_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'created_count' => count($createdTeachers),
                'failed_count' => count($errors),
                'teachers' => $createdTeachers,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create multiple subjects in bulk
     */
    public function createSubjects(array $data): array
    {
        $subjects = $data['subjects'];
        $createdSubjects = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($subjects as $index => $subjectData) {
                try {
                    // Ensure school_id is set
                    if (!isset($subjectData['school_id'])) {
                        $subjectData['school_id'] = $this->getCurrentSchoolId();
                    }

                    // Use SubjectService to create subject with all validations
                    $subject = $this->subjectService->createSubject($subjectData);

                    $createdSubjects[] = [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'subject_area' => $subject->subject_area
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $subjectData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($createdSubjects)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'created_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'created_count' => count($createdSubjects),
                'failed_count' => count($errors),
                'subjects' => $createdSubjects,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Transfer multiple students between classes
     */
    public function transferStudents(array $data): array
    {
        $transfers = $data['transfers'];
        $transferredStudents = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($transfers as $index => $transferData) {
                try {
                    $student = Student::findOrFail($transferData['student_id']);
                    $this->validateSchoolOwnership($student);

                    $fromClass = AcademicClass::findOrFail($transferData['from_class_id']);
                    $this->validateSchoolOwnership($fromClass);

                    $toClass = AcademicClass::findOrFail($transferData['to_class_id']);
                    $this->validateSchoolOwnership($toClass);

                    // Remove from old class
                    $fromClass->students()->detach($student->id);

                    // Add to new class
                    $toClass->students()->attach($student->id, [
                        'enrollment_date' => $transferData['effective_date'],
                        'status' => 'active',
                        'transfer_reason' => $transferData['reason'] ?? null
                    ]);

                    $transferredStudents[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'from_class' => $fromClass->name,
                        'to_class' => $toClass->name,
                        'effective_date' => $transferData['effective_date']
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $transferData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'transferred_count' => 0,
                    'errors' => $errors
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'transferred_count' => count($transferredStudents),
                'transfers' => $transferredStudents
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get bulk operation status
     */
    public function getOperationStatus(string $operationId): array
    {
        // This would integrate with a job queue system
        // For now, return placeholder data
        return [
            'operation_id' => $operationId,
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Operation completed successfully'
        ];
    }

    /**
     * Cancel bulk operation
     */
    public function cancelOperation(string $operationId): bool
    {
        // This would integrate with a job queue system
        // For now, return placeholder response
        return true;
    }

    /**
     * Helper methods
     */
    protected function generateStudentReportCard(Student $student, ?int $academicYearId, ?string $term): array
    {
        // This would integrate with report card generation system
        return [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'academic_year_id' => $academicYearId,
            'term' => $term,
            'generated_at' => now(),
            'download_url' => null // Would be actual download URL
        ];
    }

    protected function filterUpdateData(array $data, string $updateType): array
    {
        $allowedFields = [];

        switch ($updateType) {
            case 'personal_info':
                $allowedFields = ['first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender', 'phone', 'email'];
                break;
            case 'academic_info':
                $allowedFields = ['student_id', 'grade_level', 'enrollment_date', 'status'];
                break;
            case 'contact_info':
                $allowedFields = ['phone', 'email', 'address_json', 'emergency_contacts_json'];
                break;
            case 'all':
                $allowedFields = array_keys($data);
                break;
        }

        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Get default permissions for teachers
     */
    private function getDefaultTeacherPermissions(): array
    {
        return [
            'view_students',
            'view_classes',
            'view_grades',
            'create_grades',
            'update_grades',
            'view_attendance',
            'create_attendance',
            'update_attendance',
            'view_schedule',
            'view_assignments',
            'create_assignments',
            'update_assignments',
            'view_announcements',
            'create_announcements'
        ];
    }
}

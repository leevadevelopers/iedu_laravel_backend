<?php

namespace App\Services\V1\Academic;

use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class BulkOperationsService extends BaseAcademicService
{
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
                    $classData['school_id'] = $this->getCurrentSchoolId();
                    $class = AcademicClass::create($classData);
                    $createdClasses[] = $class;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $classData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
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
                'classes' => $createdClasses
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
                    $class = AcademicClass::findOrFail($enrollmentData['class_id']);
                    $this->validateSchoolOwnership($class);

                    $student = Student::findOrFail($enrollmentData['student_id']);
                    $this->validateSchoolOwnership($student);

                    // Check if student is already enrolled
                    if ($class->students()->where('student_id', $student->id)->exists()) {
                        $errors[] = [
                            'index' => $index,
                            'student_id' => $student->id,
                            'class_id' => $class->id,
                            'error' => 'Student is already enrolled in this class'
                        ];
                        continue;
                    }

                    $class->students()->attach($student->id, [
                        'enrollment_date' => $enrollmentData['enrollment_date'] ?? now(),
                        'status' => $enrollmentData['status'] ?? 'active'
                    ]);

                    $enrolledStudents[] = [
                        'student_id' => $student->id,
                        'class_id' => $class->id,
                        'student_name' => $student->full_name,
                        'class_name' => $class->name
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $enrollmentData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
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
                'enrollments' => $enrolledStudents
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
                    $gradeData['entered_by'] = auth()->id();

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
                    $teacherData['school_id'] = $this->getCurrentSchoolId();
                    $teacher = Teacher::create($teacherData);
                    $createdTeachers[] = $teacher;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $teacherData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
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
                'teachers' => $createdTeachers
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
                    $subjectData['school_id'] = $this->getCurrentSchoolId();
                    $subject = Subject::create($subjectData);
                    $createdSubjects[] = $subject;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'data' => $subjectData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
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
                'subjects' => $createdSubjects
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
}

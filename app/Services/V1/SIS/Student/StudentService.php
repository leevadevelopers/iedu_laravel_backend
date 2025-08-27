<?php

namespace App\Services\V1\SIS\Student;

use App\Models\V1\SIS\Student\Student;
use App\Repositories\V1\SIS\Contracts\StudentRepositoryInterface;
use App\Repositories\V1\SIS\Contracts\FamilyRelationshipRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Student Service
 *
 * Handles business logic for student information management including
 * CRUD operations, academic tracking, family relationships, and educational workflows.
 */
class StudentService
{
    protected StudentRepositoryInterface $studentRepository;
    protected FamilyRelationshipRepositoryInterface $familyRepository;

    public function __construct(
        StudentRepositoryInterface $studentRepository,
        FamilyRelationshipRepositoryInterface $familyRepository
    ) {
        $this->studentRepository = $studentRepository;
        $this->familyRepository = $familyRepository;
    }

    /**
     * Create a new student with comprehensive validation.
     */
    public function createStudent(array $data): Student
    {
        $this->validateStudentData($data);

        return DB::transaction(function () use ($data) {
            // Extract family relationship data if provided
            $familyData = $data['family_relationships'] ?? [];
            unset($data['family_relationships']);

            // Create the student
            $student = $this->studentRepository->create($data);

            // Create family relationships if provided
            if (!empty($familyData)) {
                $this->createFamilyRelationships($student->id, $familyData);
            }

            // Log the student creation
            Log::info('Student created', [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
            ]);

            return $student;
        });
    }

    /**
     * Update an existing student.
     */
    public function updateStudent(int $studentId, array $data): Student
    {
        $this->validateStudentData($data, $studentId);

        return DB::transaction(function () use ($studentId, $data) {
            // Handle family relationships separately
            $familyData = $data['family_relationships'] ?? [];
            unset($data['family_relationships']);

            // Update the student
            $student = $this->studentRepository->update($studentId, $data);

            // Update family relationships if provided
            if (isset($familyData)) {
                $this->updateFamilyRelationships($studentId, $familyData);
            }

            // Log the student update
            Log::info('Student updated', [
                'student_id' => $student->id,
                'student_number' => $student->student_number,
                'updated_fields' => array_keys($data),
            ]);

            return $student;
        });
    }

    /**
     * Get student by ID with full information.
     */
    public function getStudent(int $studentId): ?Student
    {
        $student = $this->studentRepository->find($studentId);

        if ($student) {
            $student->load([
                'currentAcademicYear',
                'familyRelationships.guardian',
                'documents' => function ($query) {
                    $query->where('status', '!=', 'archived')
                          ->orderBy('required', 'desc')
                          ->orderBy('document_type');
                }
            ]);
        }

        return $student;
    }

    /**
     * Get student by student number.
     */
    public function getStudentByNumber(string $studentNumber): ?Student
    {
        return $this->studentRepository->findByStudentNumber($studentNumber);
    }

    /**
     * Get paginated list of students with filtering and search.
     */
    public function getStudents(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if (!empty($filters['search'])) {
            return $this->studentRepository->search(
                $filters['search'],
                $filters,
                $perPage
            );
        }

        return $this->studentRepository->paginate($filters, $perPage);
    }

    /**
     * Delete a student (soft delete with business rules).
     */
    public function deleteStudent(int $studentId): bool
    {
        $student = $this->studentRepository->find($studentId);

        if (!$student) {
            throw new \InvalidArgumentException('Student not found');
        }

        // Business rule: Cannot delete enrolled students
        if ($student->enrollment_status === 'enrolled') {
            throw new \InvalidArgumentException('Cannot delete enrolled student. Change status first.');
        }

        return DB::transaction(function () use ($studentId, $student) {
            // Archive related records instead of deleting
            $this->archiveStudentRecords($studentId);

            $result = $this->studentRepository->delete($studentId);

            Log::info('Student deleted', [
                'student_id' => $studentId,
                'student_number' => $student->student_number,
                'name' => $student->full_name,
            ]);

            return $result;
        });
    }

    /**
     * Promote students to next grade level.
     */
    public function promoteStudents(array $studentIds, string $newGradeLevel, int $newAcademicYearId): array
    {
        $results = ['promoted' => 0, 'failed' => 0, 'errors' => []];

        DB::transaction(function () use ($studentIds, $newGradeLevel, $newAcademicYearId, &$results) {
            foreach ($studentIds as $studentId) {
                try {
                    $student = $this->studentRepository->find($studentId);

                    if (!$student) {
                        $results['errors'][] = "Student ID {$studentId} not found";
                        $results['failed']++;
                        continue;
                    }

                    // Validate grade progression
                    if (!$this->isValidGradeProgression($student->current_grade_level, $newGradeLevel)) {
                        $results['errors'][] = "Invalid grade progression for student {$student->student_number}";
                        $results['failed']++;
                        continue;
                    }

                    // Update student record
                    $this->studentRepository->update($studentId, [
                        'current_grade_level' => $newGradeLevel,
                        'current_academic_year_id' => $newAcademicYearId,
                    ]);

                    $results['promoted']++;

                } catch (\Exception $e) {
                    $results['errors'][] = "Error promoting student ID {$studentId}: " . $e->getMessage();
                    $results['failed']++;
                }
            }
        });

        Log::info('Bulk student promotion completed', $results);

        return $results;
    }

    /**
     * Get student academic summary with performance metrics.
     */
    public function getStudentAcademicSummary(int $studentId): array
    {
        $student = $this->studentRepository->find($studentId);

        if (!$student) {
            throw new \InvalidArgumentException('Student not found');
        }

        /** @var array $summary */
        $summary = $this->studentRepository->getAcademicSummary($studentId);

        // Add additional educational insights
        $summary['enrollment_duration_days'] = $student->admission_date->diffInDays(now());
        $summary['age'] = $student->age ?? null;
        $summary['has_special_needs'] = $student->hasSpecialNeeds();
        $summary['primary_emergency_contact'] = $student->getPrimaryEmergencyContact();

        return $summary;
    }

    /**
     * Get students requiring attention (missing documents, low attendance, etc.).
     */
    public function getStudentsRequiringAttention(): array
    {
        return [
            'missing_documents' => $this->studentRepository->getStudentsRequiringDocuments(),
            'missing_emergency_contacts' => $this->studentRepository->getStudentsWithMissingEmergencyContacts(),
            'upcoming_birthdays' => $this->studentRepository->getUpcomingBirthdays(7), // Next 7 days
        ];
    }

    /**
     * Get enrollment statistics for dashboard.
     */
    public function getEnrollmentStatistics(): array
    {
        $stats = [
            'by_grade' => $this->studentRepository->getEnrollmentStatsByGrade(),
            'by_status' => [],
            'special_needs_count' => 0,
            'total_enrolled' => 0,
        ];

        // Get enrollment status counts
        foreach (['enrolled', 'withdrawn', 'graduated', 'transferred', 'suspended'] as $status) {
            $count = $this->studentRepository->getByEnrollmentStatus($status)->count();
            $stats['by_status'][$status] = $count;

            if ($status === 'enrolled') {
                $stats['total_enrolled'] = $count;
            }
        }

        // Get special needs count
        $stats['special_needs_count'] = $this->studentRepository->getStudentsWithSpecialNeeds()->count();

        return $stats;
    }

    /**
     * Transfer student to another school.
     */
    public function transferStudent(int $studentId, array $transferData): bool
    {
        $student = $this->studentRepository->find($studentId);

        if (!$student) {
            throw new \InvalidArgumentException('Student not found');
        }

        return DB::transaction(function () use ($studentId, $transferData, $student) {
            // Update enrollment status
            $this->studentRepository->update($studentId, [
                'enrollment_status' => 'transferred',
                'expected_graduation_date' => null,
            ]);

            // Create enrollment history record
            $student->enrollmentHistory()->create([
                'school_id' => $student->school_id,
                'academic_year_id' => $student->current_academic_year_id,
                'enrollment_date' => $student->admission_date,
                'withdrawal_date' => now()->toDateString(),
                'grade_level_at_enrollment' => $student->current_grade_level,
                'grade_level_at_withdrawal' => $student->current_grade_level,
                'enrollment_type' => 'enrollment',
                'withdrawal_type' => 'transfer_out',
                'withdrawal_reason' => $transferData['reason'] ?? null,
                'next_school' => $transferData['destination_school'] ?? null,
                'final_gpa' => $student->current_gpa,
            ]);

            Log::info('Student transferred', [
                'student_id' => $studentId,
                'student_number' => $student->student_number,
                'destination' => $transferData['destination_school'] ?? 'Unknown',
            ]);

            return true;
        });
    }

    /**
     * Validate student data with educational business rules.
     */
    protected function validateStudentData(array $data, ?int $studentId = null): void
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'current_grade_level' => 'required|string|max:20',
            'admission_date' => 'required|date',
            'enrollment_status' => 'required|in:enrolled,transferred,graduated,withdrawn,suspended',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'student_number' => 'nullable|string|max:50',
            'school_id' => 'required|exists:schools,id',
            'user_id' => 'required|exists:users,id',
            'tenant_id' => 'required|exists:tenants,id',
        ];

        $messages = [
            'first_name.required' => 'Student first name is required',
            'last_name.required' => 'Student last name is required',
            'date_of_birth.required' => 'Student date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'current_grade_level.required' => 'Grade level is required',
            'admission_date.required' => 'Admission date is required',
            'enrollment_status.required' => 'Enrollment status is required',
            'enrollment_status.in' => 'Invalid enrollment status',
            'school_id.required' => 'School ID is required',
            'school_id.exists' => 'Selected school does not exist',
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'Selected user does not exist',
            'tenant_id.required' => 'Tenant ID is required',
            'tenant_id.exists' => 'Selected tenant does not exist',
        ];

        // Add unique student number validation if provided
        if (!empty($data['student_number'])) {
            $uniqueRule = 'unique:students,student_number';
            if ($studentId) {
                $uniqueRule .= ',' . $studentId;
            }
            $rules['student_number'] .= '|' . $uniqueRule;
            $messages['student_number.unique'] = 'Student number is already in use';
        }

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business rule validations
        $this->validateEducationalBusinessRules($data, $studentId);
    }

    /**
     * Validate educational business rules.
     */
    protected function validateEducationalBusinessRules(array $data, ?int $studentId = null): void
    {
        // Validate age for grade level
        if (isset($data['date_of_birth']) && isset($data['current_grade_level'])) {
            if (!$this->isValidAgeForGrade($data['date_of_birth'], $data['current_grade_level'])) {
                throw new ValidationException(validator([], [], [
                    'age_grade_mismatch' => 'Student age is not appropriate for the selected grade level'
                ]));
            }
        }

        // Validate admission date
        if (isset($data['admission_date'])) {
            if (strtotime($data['admission_date']) > time()) {
                throw new ValidationException(validator([], [], [
                    'admission_date.future' => 'Admission date cannot be in the future'
                ]));
            }
        }
    }

    /**
     * Check if age is appropriate for grade level.
     */
    protected function isValidAgeForGrade(string $dateOfBirth, string $gradeLevel): bool
    {
        $age = \Carbon\Carbon::parse($dateOfBirth)->age;

        $ageRanges = [
            'K' => [4, 7], 'Pre-K' => [3, 5],
            '1' => [5, 8], '2' => [6, 9], '3' => [7, 10], '4' => [8, 11], '5' => [9, 12],
            '6' => [10, 13], '7' => [11, 14], '8' => [12, 15],
            '9' => [13, 16], '10' => [14, 17], '11' => [15, 18], '12' => [16, 19],
        ];

        if (!isset($ageRanges[$gradeLevel])) {
            return true; // Allow if grade level not in standard range
        }

        [$minAge, $maxAge] = $ageRanges[$gradeLevel];
        return $age >= $minAge && $age <= $maxAge;
    }

    /**
     * Check if grade progression is valid.
     */
    protected function isValidGradeProgression(string $currentGrade, string $newGrade): bool
    {
        $gradeOrder = ['Pre-K', 'K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

        $currentIndex = array_search($currentGrade, $gradeOrder);
        $newIndex = array_search($newGrade, $gradeOrder);

        // Allow progression or lateral movement
        return $newIndex !== false && $currentIndex !== false && $newIndex >= $currentIndex;
    }

    /**
     * Create family relationships for a student.
     */
    protected function createFamilyRelationships(int $studentId, array $familyData): void
    {
        foreach ($familyData as $relationship) {
            $relationship['student_id'] = $studentId;
            $this->familyRepository->create($relationship);
        }
    }

    /**
     * Update family relationships for a student.
     */
    protected function updateFamilyRelationships(int $studentId, array $familyData): void
    {
        // This would typically involve more complex logic to handle updates, deletions, and additions
        // For now, this is a placeholder for the family relationship update logic
    }

    /**
     * Archive student records before deletion.
     */
    protected function archiveStudentRecords(int $studentId): void
    {
        // Archive related records - this would be implemented based on school policy
        // For example: move grades to historical table, archive documents, etc.
    }
}

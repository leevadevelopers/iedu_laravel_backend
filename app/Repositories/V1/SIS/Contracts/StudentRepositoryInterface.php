<?php

namespace App\Repositories\V1\SIS\Contracts;

use App\Models\V1\SIS\Student\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Student Repository Interface
 *
 * Defines the contract for student data access operations
 * including CRUD operations, search, filtering, and educational queries.
 */
interface StudentRepositoryInterface
{
    /**
     * Find student by ID with school scoping.
     */
    public function find(int $id): ?Student;

    /**
     * Find student by student number within school.
     */
    public function findByStudentNumber(string $studentNumber): ?Student;

    /**
     * Create a new student record.
     */
    public function create(array $data): Student;

    /**
     * Update an existing student record.
     */
    public function update(int $id, array $data): Student;

    /**
     * Delete a student record (soft delete).
     */
    public function delete(int $id): bool;

    /**
     * Get paginated list of students with optional filtering.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Search students by name, student number, or other criteria.
     */
    public function search(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get students by enrollment status.
     */
    public function getByEnrollmentStatus(string $status): Collection;

    /**
     * Get students by grade level.
     */
    public function getByGradeLevel(string $gradeLevel): Collection;

    /**
     * Get students requiring document verification.
     */
    public function getStudentsRequiringDocuments(): Collection;

    /**
     * Get students with missing emergency contacts.
     */
    public function getStudentsWithMissingEmergencyContacts(): Collection;

    /**
     * Get student enrollment statistics by grade level.
     */
    public function getEnrollmentStatsByGrade(): array;

    /**
     * Get students with special educational needs.
     */
    public function getStudentsWithSpecialNeeds(): Collection;

    /**
     * Get students by academic year.
     */
    public function getByAcademicYear(int $academicYearId): Collection;

    /**
     * Bulk update students (for operations like grade promotion).
     */
    public function bulkUpdate(array $studentIds, array $data): bool;

    /**
     * Get student academic summary.
     */
    public function getAcademicSummary(int $studentId): array;

    /**
     * Check if student number is available within school.
     */
    public function isStudentNumberAvailable(string $studentNumber): bool;

    /**
     * Get students with upcoming birthdays.
     */
    public function getUpcomingBirthdays(int $days = 30): Collection;
}

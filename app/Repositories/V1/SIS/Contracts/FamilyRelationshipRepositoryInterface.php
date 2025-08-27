<?php

namespace App\Repositories\V1\SIS\Contracts;

use App\Models\V1\SIS\Student\FamilyRelationship;
use Illuminate\Database\Eloquent\Collection;

/**
 * Family Relationship Repository Interface
 *
 * Defines the contract for family relationship data access operations.
 */
interface FamilyRelationshipRepositoryInterface
{
    /**
     * Find family relationship by ID.
     */
    public function find(int $id): ?FamilyRelationship;

    /**
     * Create a new family relationship.
     */
    public function create(array $data): FamilyRelationship;

    /**
     * Update an existing family relationship.
     */
    public function update(int $id, array $data): FamilyRelationship;

    /**
     * Delete a family relationship.
     */
    public function delete(int $id): bool;

    /**
     * Get all family relationships for a student.
     */
    public function getByStudent(int $studentId): Collection;

    /**
     * Get all students for a guardian.
     */
    public function getStudentsByGuardian(int $guardianUserId): Collection;

    /**
     * Get primary contact for a student.
     */
    public function getPrimaryContact(int $studentId): ?FamilyRelationship;

    /**
     * Get emergency contacts for a student.
     */
    public function getEmergencyContacts(int $studentId): Collection;

    /**
     * Check if a user is authorized to access a student's information.
     */
    public function isAuthorizedForStudent(int $guardianUserId, int $studentId, string $accessType = 'academic'): bool;
}

<?php

namespace App\Repositories\V1\SIS\Eloquent;

use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Repositories\V1\SIS\Contracts\FamilyRelationshipRepositoryInterface;
use App\Services\SchoolContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent Family Relationship Repository
 *
 * Implementation of family relationship repository using Eloquent ORM.
 */
class EloquentFamilyRelationshipRepository implements FamilyRelationshipRepositoryInterface
{
    protected FamilyRelationship $model;
    protected SchoolContextService $schoolContext;

    public function __construct(FamilyRelationship $model, SchoolContextService $schoolContext)
    {
        $this->model = $model;
        $this->schoolContext = $schoolContext;
    }

    /**
     * Get a new query builder with school scoping applied.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('school_id', $this->schoolContext->getCurrentSchoolId());
    }

    /**
     * Find family relationship by ID.
     */
    public function find(int $id): ?FamilyRelationship
    {
        return $this->newQuery()->with(['student', 'guardian'])->find($id);
    }

    /**
     * Create a new family relationship.
     */
    public function create(array $data): FamilyRelationship
    {
        $data['school_id'] = $this->schoolContext->getCurrentSchoolId();

        return $this->model->create($data);
    }

    /**
     * Update an existing family relationship.
     */
    public function update(int $id, array $data): FamilyRelationship
    {
        $relationship = $this->newQuery()->findOrFail($id);

        $relationship->update($data);

        return $relationship->fresh();
    }

    /**
     * Delete a family relationship.
     */
    public function delete(int $id): bool
    {
        $relationship = $this->newQuery()->findOrFail($id);

        return $relationship->delete();
    }

    /**
     * Get all family relationships for a student.
     */
    public function getByStudent(int $studentId): Collection
    {
        return $this->newQuery()
            ->with(['guardian'])
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->orderBy('primary_contact', 'desc')
            ->orderBy('relationship_type')
            ->get();
    }

    /**
     * Get all students for a guardian.
     */
    public function getStudentsByGuardian(int $guardianUserId): Collection
    {
        return $this->newQuery()
            ->with(['student'])
            ->where('guardian_user_id', $guardianUserId)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get primary contact for a student.
     */
    public function getPrimaryContact(int $studentId): ?FamilyRelationship
    {
        return $this->newQuery()
            ->with(['guardian'])
            ->where('student_id', $studentId)
            ->where('primary_contact', true)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Get emergency contacts for a student.
     */
    public function getEmergencyContacts(int $studentId): Collection
    {
        return $this->newQuery()
            ->with(['guardian'])
            ->where('student_id', $studentId)
            ->where('emergency_contact', true)
            ->where('status', 'active')
            ->orderBy('primary_contact', 'desc')
            ->get();
    }

    /**
     * Check if a user is authorized to access a student's information.
     */
    public function isAuthorizedForStudent(int $guardianUserId, int $studentId, string $accessType = 'academic'): bool
    {
        $relationship = $this->newQuery()
            ->where('guardian_user_id', $guardianUserId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->first();

        if (!$relationship) {
            return false;
        }

        switch ($accessType) {
            case 'academic':
                return $relationship->academic_access;
            case 'medical':
                return $relationship->medical_access;
            case 'pickup':
                return $relationship->pickup_authorized;
            default:
                return true; // General access
        }
    }
}

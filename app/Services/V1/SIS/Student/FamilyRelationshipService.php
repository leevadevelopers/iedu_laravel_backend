<?php

namespace App\Services\V1\SIS\Student;

use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Repositories\V1\SIS\Contracts\FamilyRelationshipRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Family Relationship Service
 *
 * Manages family relationships and guardian permissions within the educational system.
 * Handles FERPA compliance and educational access controls.
 */
class FamilyRelationshipService
{
    protected FamilyRelationshipRepositoryInterface $repository;

    public function __construct(FamilyRelationshipRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Create a new family relationship.
     */
    public function createRelationship(array $data): FamilyRelationship
    {
        $this->validateRelationshipData($data);

        return DB::transaction(function () use ($data) {
            // Ensure only one primary contact per student
            if (!empty($data['primary_contact']) && $data['primary_contact']) {
                $this->clearPrimaryContact($data['student_id']);
            }

            $relationship = $this->repository->create($data);

            Log::info('Family relationship created', [
                'relationship_id' => $relationship->id,
                'student_id' => $relationship->student_id,
                'guardian_id' => $relationship->guardian_user_id,
                'relationship_type' => $relationship->relationship_type,
            ]);

            return $relationship;
        });
    }

    /**
     * Update an existing family relationship.
     */
    public function updateRelationship(int $relationshipId, array $data): FamilyRelationship
    {
        $this->validateRelationshipData($data, $relationshipId);

        return DB::transaction(function () use ($relationshipId, $data) {
            $relationship = $this->repository->find($relationshipId);

            if (!$relationship) {
                throw new \InvalidArgumentException('Family relationship not found');
            }

            // Handle primary contact changes
            if (isset($data['primary_contact']) && $data['primary_contact']) {
                $this->clearPrimaryContact($relationship->student_id, $relationshipId);
            }

            $updatedRelationship = $this->repository->update($relationshipId, $data);

            Log::info('Family relationship updated', [
                'relationship_id' => $relationshipId,
                'updated_fields' => array_keys($data),
            ]);

            return $updatedRelationship;
        });
    }

    /**
     * Get all family relationships for a student.
     */
    public function getStudentRelationships(int $studentId): Collection
    {
        return $this->repository->getByStudent($studentId);
    }

    /**
     * Get all students for a guardian.
     */
    public function getGuardianStudents(int $guardianUserId): Collection
    {
        return $this->repository->getStudentsByGuardian($guardianUserId);
    }

    /**
     * Delete a family relationship.
     */
    public function deleteRelationship(int $relationshipId): bool
    {
        $relationship = $this->repository->find($relationshipId);

        if (!$relationship) {
            throw new \InvalidArgumentException('Family relationship not found');
        }

        // Business rule: Cannot delete the only emergency contact
        if ($relationship->emergency_contact) {
            $emergencyContacts = $this->repository->getEmergencyContacts($relationship->student_id);
            if ($emergencyContacts->count() <= 1) {
                throw new \InvalidArgumentException('Cannot delete the only emergency contact for this student');
            }
        }

        $result = $this->repository->delete($relationshipId);

        if ($result) {
            Log::info('Family relationship deleted', [
                'relationship_id' => $relationshipId,
                'student_id' => $relationship->student_id,
            ]);
        }

        return $result;
    }

    /**
     * Get emergency contacts for a student.
     */
    public function getEmergencyContacts(int $studentId): Collection
    {
        return $this->repository->getEmergencyContacts($studentId);
    }

    /**
     * Get primary contact for a student.
     */
    public function getPrimaryContact(int $studentId): ?FamilyRelationship
    {
        return $this->repository->getPrimaryContact($studentId);
    }

    /**
     * Check if a guardian is authorized to access student information.
     */
    public function isAuthorizedForStudent(int $guardianUserId, int $studentId, string $accessType = 'academic'): bool
    {
        return $this->repository->isAuthorizedForStudent($guardianUserId, $studentId, $accessType);
    }

    /**
     * Set primary contact for a student.
     */
    public function setPrimaryContact(int $relationshipId): FamilyRelationship
    {
        $relationship = $this->repository->find($relationshipId);

        if (!$relationship) {
            throw new \InvalidArgumentException('Family relationship not found');
        }

        return DB::transaction(function () use ($relationship, $relationshipId) {
            // Clear existing primary contact
            $this->clearPrimaryContact($relationship->student_id, $relationshipId);

            // Set new primary contact
            return $this->repository->update($relationshipId, ['primary_contact' => true]);
        });
    }

    /**
     * Grant specific permission to a guardian.
     */
    public function grantPermission(int $relationshipId, string $permissionType, bool $grant = true): FamilyRelationship
    {
        $validPermissions = ['academic_access', 'medical_access', 'pickup_authorized', 'emergency_contact'];

        if (!in_array($permissionType, $validPermissions)) {
            throw new \InvalidArgumentException('Invalid permission type');
        }

        $relationship = $this->repository->update($relationshipId, [$permissionType => $grant]);

        Log::info('Guardian permission updated', [
            'relationship_id' => $relationshipId,
            'permission_type' => $permissionType,
            'granted' => $grant,
        ]);

        return $relationship;
    }

    /**
     * Get relationship summary for a student.
     */
    public function getStudentFamilySummary(int $studentId): array
    {
        $relationships = $this->getStudentRelationships($studentId);

        $summary = [
            'total_relationships' => $relationships->count(),
            'primary_contact' => null,
            'emergency_contacts' => [],
            'authorized_pickup' => [],
            'academic_access' => [],
            'medical_access' => [],
        ];

        foreach ($relationships as $relationship) {
            if ($relationship->primary_contact) {
                $summary['primary_contact'] = $relationship;
            }

            if ($relationship->emergency_contact) {
                $summary['emergency_contacts'][] = $relationship;
            }

            if ($relationship->pickup_authorized) {
                $summary['authorized_pickup'][] = $relationship;
            }

            if ($relationship->academic_access) {
                $summary['academic_access'][] = $relationship;
            }

            if ($relationship->medical_access) {
                $summary['medical_access'][] = $relationship;
            }
        }

        return $summary;
    }

    /**
     * Validate family relationship data.
     */
    protected function validateRelationshipData(array $data, ?int $relationshipId = null): void
    {
        $rules = [
            'student_id' => 'required|exists:students,id',
            'guardian_user_id' => 'required|exists:users,id',
            'relationship_type' => 'required|in:mother,father,stepmother,stepfather,grandmother,grandfather,aunt,uncle,guardian,foster_parent,other',
            'relationship_description' => 'nullable|string|max:100',
            'primary_contact' => 'boolean',
            'emergency_contact' => 'boolean',
            'pickup_authorized' => 'boolean',
            'academic_access' => 'boolean',
            'medical_access' => 'boolean',
            'custody_rights' => 'boolean',
            'financial_responsibility' => 'boolean',
            'status' => 'in:active,inactive,archived',
        ];

        $messages = [
            'student_id.required' => 'Student is required',
            'student_id.exists' => 'Selected student does not exist',
            'guardian_user_id.required' => 'Guardian is required',
            'guardian_user_id.exists' => 'Selected guardian does not exist',
            'relationship_type.required' => 'Relationship type is required',
            'relationship_type.in' => 'Invalid relationship type',
        ];

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business rule validations
        $this->validateEducationalRelationshipRules($data, $relationshipId);
    }

    /**
     * Validate educational relationship business rules.
     */
    protected function validateEducationalRelationshipRules(array $data, ?int $relationshipId = null): void
    {
        // Validate that guardian is not also a student in the same school
        if (isset($data['student_id']) && isset($data['guardian_user_id'])) {
            // This would include logic to check if guardian is also a student
        }

        // Validate custody rights alignment with permissions
        if (isset($data['custody_rights']) && $data['custody_rights']) {
            // Ensure custody rights holders have appropriate permissions
            if (!($data['academic_access'] ?? true)) {
                throw new ValidationException(validator([], [], [
                    'custody_academic_access' => 'Guardians with custody rights must have academic access'
                ]));
            }
        }
    }

    /**
     * Clear primary contact for a student (except optionally one relationship).
     */
    protected function clearPrimaryContact(int $studentId, ?int $exceptRelationshipId = null): void
    {
        $relationships = $this->repository->getByStudent($studentId);

        foreach ($relationships as $relationship) {
            if ($relationship->primary_contact && $relationship->id !== $exceptRelationshipId) {
                $this->repository->update($relationship->id, ['primary_contact' => false]);
            }
        }
    }
}

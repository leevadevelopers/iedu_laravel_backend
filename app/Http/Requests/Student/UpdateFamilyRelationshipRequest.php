<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Family Relationship Request
 * 
 * Validates data for updating family relationships with change
 * tracking and educational access validation.
 */
class UpdateFamilyRelationshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $relationship = $this->route('family_relationship');
        return auth('api')->user()->can('update', $relationship);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Relationship Definition (some fields may not be updatable)
            'relationship_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'mother', 'father', 'stepmother', 'stepfather',
                    'grandmother', 'grandfather', 'aunt', 'uncle',
                    'guardian', 'foster_parent', 'other'
                ])
            ],
            'relationship_description' => [
                'nullable',
                'string',
                'max:100'
            ],
            
            // Contact & Emergency Permissions
            'primary_contact' => [
                'sometimes',
                'boolean'
            ],
            'emergency_contact' => [
                'sometimes',
                'boolean'
            ],
            'pickup_authorized' => [
                'sometimes',
                'boolean'
            ],
            
            // Educational Access Permissions
            'academic_access' => [
                'sometimes',
                'boolean'
            ],
            'medical_access' => [
                'sometimes',
                'boolean'
            ],
            
            // Legal & Financial Responsibilities
            'custody_rights' => [
                'sometimes',
                'boolean'
            ],
            'custody_details_json' => [
                'nullable',
                'array'
            ],
            'custody_details_json.custody_type' => [
                'nullable',
                'string',
                Rule::in(['full', 'joint', 'partial', 'temporary', 'none'])
            ],
            'custody_details_json.legal_documents' => [
                'nullable',
                'array'
            ],
            'custody_details_json.restrictions' => [
                'nullable',
                'string',
                'max:500'
            ],
            
            'financial_responsibility' => [
                'sometimes',
                'boolean'
            ],
            
            // Communication Preferences
            'communication_preferences_json' => [
                'nullable',
                'array'
            ],
            'communication_preferences_json.preferred_method' => [
                'nullable',
                'string',
                Rule::in(['email', 'phone', 'sms', 'app', 'mail'])
            ],
            'communication_preferences_json.preferred_language' => [
                'nullable',
                'string',
                'max:50'
            ],
            'communication_preferences_json.contact_times' => [
                'nullable',
                'array'
            ],
            'communication_preferences_json.emergency_only' => [
                'nullable',
                'boolean'
            ],
            
            // Status
            'status' => [
                'sometimes',
                'string',
                Rule::in(['active', 'inactive', 'archived'])
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Relationship Type
            'relationship_type.required' => 'Relationship type is required',
            'relationship_type.in' => 'Invalid relationship type selected',
            'relationship_description.max' => 'Relationship description cannot exceed 100 characters',
            
            // Permissions
            'primary_contact.boolean' => 'Primary contact must be yes or no',
            'emergency_contact.boolean' => 'Emergency contact must be yes or no',
            'pickup_authorized.boolean' => 'Pickup authorization must be yes or no',
            'academic_access.boolean' => 'Academic access must be yes or no',
            'medical_access.boolean' => 'Medical access must be yes or no',
            'custody_rights.boolean' => 'Custody rights must be yes or no',
            'financial_responsibility.boolean' => 'Financial responsibility must be yes or no',
            
            // Custody Details
            'custody_details_json.custody_type.in' => 'Invalid custody type selected',
            'custody_details_json.restrictions.max' => 'Custody restrictions cannot exceed 500 characters',
            
            // Communication Preferences
            'communication_preferences_json.preferred_method.in' => 'Invalid communication method selected',
            'communication_preferences_json.preferred_language.max' => 'Preferred language cannot exceed 50 characters',
            
            // Status
            'status.in' => 'Invalid status selected'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $currentRelationship = $this->route('family_relationship');
            
            // Ensure custody rights holders have academic access
            $custodyRights = $this->has('custody_rights') ? $this->custody_rights : $currentRelationship->custody_rights;
            $academicAccess = $this->has('academic_access') ? $this->academic_access : $currentRelationship->academic_access;
            
            if ($custodyRights && !$academicAccess) {
                $validator->errors()->add(
                    'academic_access',
                    'Guardians with custody rights must have academic access'
                );
            }

            // Validate primary contact business rules
            if ($this->has('primary_contact') && $this->primary_contact) {
                $existingPrimary = \App\Models\Student\FamilyRelationship::where([
                    'student_id' => $currentRelationship->student_id,
                    'primary_contact' => true,
                    'status' => 'active'
                ])->where('id', '!=', $currentRelationship->id)->exists();

                if ($existingPrimary) {
                    $validator->errors()->add(
                        'primary_contact',
                        'This student already has a primary contact. Please unset the existing primary contact first.'
                    );
                }
            }

            // Require academic access for primary contacts
            $isPrimary = $this->has('primary_contact') ? $this->primary_contact : $currentRelationship->primary_contact;
            if ($isPrimary && !$academicAccess) {
                $validator->errors()->add(
                    'academic_access',
                    'Primary contacts must have academic access'
                );
            }

            // Validate medical access requires emergency contact status
            $medicalAccess = $this->has('medical_access') ? $this->medical_access : $currentRelationship->medical_access;
            $emergencyContact = $this->has('emergency_contact') ? $this->emergency_contact : $currentRelationship->emergency_contact;
            
            if ($medicalAccess && !$emergencyContact) {
                $validator->errors()->add(
                    'emergency_contact',
                    'Medical access requires emergency contact status'
                );
            }

            // Validate that description is required for 'other' relationship type
            $relationshipType = $this->has('relationship_type') ? $this->relationship_type : $currentRelationship->relationship_type;
            $description = $this->has('relationship_description') ? $this->relationship_description : $currentRelationship->relationship_description;
            
            if ($relationshipType === 'other' && empty($description)) {
                $validator->errors()->add(
                    'relationship_description',
                    'Relationship description is required when type is "other"'
                );
            }

            // Don't allow removing the last emergency contact
            if ($this->has('emergency_contact') && !$this->emergency_contact && $currentRelationship->emergency_contact) {
                $remainingEmergencyContacts = \App\Models\Student\FamilyRelationship::where([
                    'student_id' => $currentRelationship->student_id,
                    'emergency_contact' => true,
                    'status' => 'active'
                ])->where('id', '!=', $currentRelationship->id)->count();

                if ($remainingEmergencyContacts === 0) {
                    $validator->errors()->add(
                        'emergency_contact',
                        'Cannot remove the last emergency contact for this student'
                    );
                }
            }

            // Validate custody details when custody rights are granted
            if ($custodyRights) {
                $custodyDetails = $this->has('custody_details_json') ? $this->custody_details_json : $currentRelationship->custody_details_json;
                
                if (empty($custodyDetails) || empty($custodyDetails['custody_type'])) {
                    $validator->errors()->add(
                        'custody_details_json.custody_type',
                        'Custody type is required when custody rights are granted'
                    );
                }
            }
        });
    }
}

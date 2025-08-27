<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Family Relationship Request
 * 
 * Validates data for creating family relationships with educational
 * access controls and FERPA compliance validation.
 */
class StoreFamilyRelationshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->user()->can('create', \App\Models\Student\FamilyRelationship::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Core Relationship Information
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(function ($query) {
                    $query->where('school_id', auth('api')->user()->school_id);
                })
            ],
            'guardian_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('school_id', auth('api')->user()->school_id)
                          ->where('user_type', 'parent');
                })
            ],
            
            // Relationship Definition
            'relationship_type' => [
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
                'boolean'
            ],
            'emergency_contact' => [
                'boolean'
            ],
            'pickup_authorized' => [
                'boolean'
            ],
            
            // Educational Access Permissions
            'academic_access' => [
                'boolean'
            ],
            'medical_access' => [
                'boolean'
            ],
            
            // Legal & Financial Responsibilities
            'custody_rights' => [
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
            // Core Information
            'student_id.required' => 'Student selection is required',
            'student_id.exists' => 'Selected student does not exist or is not accessible',
            'guardian_user_id.required' => 'Guardian selection is required',
            'guardian_user_id.exists' => 'Selected guardian does not exist or is not a parent user',
            
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
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'student',
            'guardian_user_id' => 'guardian',
            'relationship_type' => 'relationship type',
            'relationship_description' => 'relationship description',
            'primary_contact' => 'primary contact',
            'emergency_contact' => 'emergency contact',
            'pickup_authorized' => 'pickup authorization',
            'academic_access' => 'academic access',
            'medical_access' => 'medical access',
            'custody_rights' => 'custody rights',
            'financial_responsibility' => 'financial responsibility',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure custody rights holders have academic access
            if ($this->custody_rights && !$this->academic_access) {
                $validator->errors()->add(
                    'academic_access',
                    'Guardians with custody rights must have academic access'
                );
            }

            // Validate that guardian is not already related to this student with same relationship type
            if ($this->student_id && $this->guardian_user_id && $this->relationship_type) {
                $existing = \App\Models\Student\FamilyRelationship::where([
                    'student_id' => $this->student_id,
                    'guardian_user_id' => $this->guardian_user_id,
                    'relationship_type' => $this->relationship_type,
                    'status' => 'active'
                ])->exists();

                if ($existing) {
                    $validator->errors()->add(
                        'relationship_type',
                        'This guardian already has this relationship type with the student'
                    );
                }
            }

            // Validate primary contact business rules
            if ($this->primary_contact && $this->student_id) {
                $existingPrimary = \App\Models\Student\FamilyRelationship::where([
                    'student_id' => $this->student_id,
                    'primary_contact' => true,
                    'status' => 'active'
                ])->exists();

                if ($existingPrimary) {
                    $validator->errors()->add(
                        'primary_contact',
                        'This student already has a primary contact. Please unset the existing primary contact first.'
                    );
                }
            }

            // Require academic access for primary contacts
            if ($this->primary_contact && !$this->academic_access) {
                $validator->errors()->add(
                    'academic_access',
                    'Primary contacts must have academic access'
                );
            }

            // Validate medical access requires emergency contact status
            if ($this->medical_access && !$this->emergency_contact) {
                $validator->errors()->add(
                    'emergency_contact',
                    'Medical access requires emergency contact status'
                );
            }

            // Validate that description is required for 'other' relationship type
            if ($this->relationship_type === 'other' && empty($this->relationship_description)) {
                $validator->errors()->add(
                    'relationship_description',
                    'Relationship description is required when type is "other"'
                );
            }

            // Validate custody details are provided when custody rights are true
            if ($this->custody_rights && (empty($this->custody_details_json) || empty($this->custody_details_json['custody_type']))) {
                $validator->errors()->add(
                    'custody_details_json.custody_type',
                    'Custody type is required when custody rights are granted'
                );
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values for boolean fields
        $this->merge([
            'primary_contact' => $this->boolean('primary_contact'),
            'emergency_contact' => $this->boolean('emergency_contact'),
            'pickup_authorized' => $this->boolean('pickup_authorized'),
            'academic_access' => $this->boolean('academic_access', true), // Default to true
            'medical_access' => $this->boolean('medical_access'),
            'custody_rights' => $this->boolean('custody_rights'),
            'financial_responsibility' => $this->boolean('financial_responsibility'),
            'status' => $this->input('status', 'active'),
        ]);
    }
}

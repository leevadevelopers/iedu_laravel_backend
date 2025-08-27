<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store Student Request
 * 
 * Validates data for creating new students with comprehensive educational
 * validation rules and business logic constraints.
 */
class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->user()->can('create', \App\Models\Student::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required Basic Information
            'first_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\'-]+$/'
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\'-]+$/'
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\'-]+$/'
            ],
            'preferred_name' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\'-]+$/'
            ],
            
            // Personal Information
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
                'after:' . now()->subYears(25)->toDateString(),
            ],
            'birth_place' => [
                'nullable',
                'string',
                'max:255'
            ],
            'gender' => [
                'nullable',
                Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])
            ],
            'nationality' => [
                'nullable',
                'string',
                'max:100'
            ],
            
            // Contact Information
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('students')->where(function ($query) {
                    return $query->where('school_id', auth('api')->user()->school_id);
                })
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\d\s\+\-\(\)]+$/'
            ],
            
            // Address Information
            'address_json' => 'nullable|array',
            'address_json.street' => 'nullable|string|max:255',
            'address_json.city' => 'nullable|string|max:100',
            'address_json.state' => 'nullable|string|max:100',
            'address_json.postal_code' => 'nullable|string|max:20',
            'address_json.country' => 'nullable|string|max:100',
            
            // Academic Information
            'admission_date' => [
                'required',
                'date',
                'before_or_equal:today'
            ],
            'current_grade_level' => [
                'required',
                'string',
                'max:20',
                Rule::in(['Pre-K', 'K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'])
            ],
            'current_academic_year_id' => [
                'nullable',
                'integer',
                'exists:academic_years,id'
            ],
            'enrollment_status' => [
                'required',
                Rule::in(['enrolled', 'transferred', 'graduated', 'withdrawn', 'suspended'])
            ],
            'expected_graduation_date' => [
                'nullable',
                'date',
                'after:today'
            ],
            
            // Student Number (optional, will be auto-generated if not provided)
            'student_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('students')->where(function ($query) {
                    return $query->where('school_id', auth('api')->user()->school_id);
                })
            ],
            'government_id' => [
                'nullable',
                'string',
                'max:50'
            ],
            
            // Educational Profile
            'learning_profile_json' => 'nullable|array',
            'learning_profile_json.learning_style' => 'nullable|string|in:visual,auditory,kinesthetic,mixed',
            'learning_profile_json.strengths' => 'nullable|array',
            'learning_profile_json.challenges' => 'nullable|array',
            
            'accommodation_needs_json' => 'nullable|array',
            'accommodation_needs_json.iep' => 'nullable|boolean',
            'accommodation_needs_json.section_504' => 'nullable|boolean',
            'accommodation_needs_json.accommodations' => 'nullable|array',
            
            'language_profile_json' => 'nullable|array',
            'language_profile_json.primary_language' => 'nullable|string|max:50',
            'language_profile_json.home_language' => 'nullable|string|max:50',
            'language_profile_json.esl_status' => 'nullable|boolean',
            'language_profile_json.proficiency_level' => 'nullable|string|in:beginner,intermediate,advanced,native',
            
            // Health & Safety Information
            'medical_information_json' => 'nullable|array',
            'medical_information_json.allergies' => 'nullable|array',
            'medical_information_json.medications' => 'nullable|array',
            'medical_information_json.medical_conditions' => 'nullable|array',
            'medical_information_json.emergency_medical_info' => 'nullable|string|max:1000',
            
            'emergency_contacts_json' => 'nullable|array|min:1',
            'emergency_contacts_json.*.name' => 'required|string|max:200',
            'emergency_contacts_json.*.relationship' => 'required|string|max:100',
            'emergency_contacts_json.*.phone' => 'required|string|max:20',
            'emergency_contacts_json.*.email' => 'nullable|email|max:255',
            'emergency_contacts_json.*.is_primary' => 'nullable|boolean',
            'emergency_contacts_json.*.can_pickup' => 'nullable|boolean',
            
            'special_circumstances_json' => 'nullable|array',
            'special_circumstances_json.custody_notes' => 'nullable|string|max:1000',
            'special_circumstances_json.safety_concerns' => 'nullable|string|max:1000',
            
            // Family Relationships (optional, can be added later)
            'family_relationships' => 'nullable|array',
            'family_relationships.*.guardian_user_id' => 'required|integer|exists:users,id',
            'family_relationships.*.relationship_type' => [
                'required',
                'string',
                Rule::in(['mother', 'father', 'stepmother', 'stepfather', 'grandmother', 'grandfather', 'aunt', 'uncle', 'guardian', 'foster_parent', 'other'])
            ],
            'family_relationships.*.primary_contact' => 'nullable|boolean',
            'family_relationships.*.emergency_contact' => 'nullable|boolean',
            'family_relationships.*.pickup_authorized' => 'nullable|boolean',
            'family_relationships.*.academic_access' => 'nullable|boolean',
            'family_relationships.*.medical_access' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Basic Information Messages
            'first_name.required' => 'Student first name is required',
            'first_name.regex' => 'First name must contain only letters, spaces, hyphens, and apostrophes',
            'last_name.required' => 'Student last name is required',
            'last_name.regex' => 'Last name must contain only letters, spaces, hyphens, and apostrophes',
            'middle_name.regex' => 'Middle name must contain only letters, spaces, hyphens, and apostrophes',
            'preferred_name.regex' => 'Preferred name must contain only letters, spaces, hyphens, and apostrophes',
            
            // Date Validation Messages
            'date_of_birth.required' => 'Date of birth is required',
            'date_of_birth.before' => 'Date of birth must be in the past',
            'date_of_birth.after' => 'Student cannot be older than 25 years for K-12 enrollment',
            'admission_date.required' => 'Admission date is required',
            'admission_date.before_or_equal' => 'Admission date cannot be in the future',
            'expected_graduation_date.after' => 'Expected graduation date must be in the future',
            
            // Contact Information Messages
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered for another student',
            'phone.regex' => 'Phone number format is invalid',
            
            // Academic Information Messages
            'current_grade_level.required' => 'Grade level is required',
            'current_grade_level.in' => 'Invalid grade level selected',
            'enrollment_status.required' => 'Enrollment status is required',
            'enrollment_status.in' => 'Invalid enrollment status',
            'student_number.unique' => 'Student number is already in use',
            'current_academic_year_id.exists' => 'Selected academic year does not exist',
            
            // Emergency Contacts Messages
            'emergency_contacts_json.min' => 'At least one emergency contact is required',
            'emergency_contacts_json.*.name.required' => 'Emergency contact name is required',
            'emergency_contacts_json.*.relationship.required' => 'Emergency contact relationship is required',
            'emergency_contacts_json.*.phone.required' => 'Emergency contact phone number is required',
            'emergency_contacts_json.*.email.email' => 'Emergency contact email must be valid',
            
            // Family Relationships Messages
            'family_relationships.*.guardian_user_id.required' => 'Guardian selection is required',
            'family_relationships.*.guardian_user_id.exists' => 'Selected guardian does not exist',
            'family_relationships.*.relationship_type.required' => 'Relationship type is required',
            'family_relationships.*.relationship_type.in' => 'Invalid relationship type selected',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'middle_name' => 'middle name',
            'preferred_name' => 'preferred name',
            'date_of_birth' => 'date of birth',
            'birth_place' => 'place of birth',
            'admission_date' => 'admission date',
            'current_grade_level' => 'grade level',
            'enrollment_status' => 'enrollment status',
            'expected_graduation_date' => 'expected graduation date',
            'student_number' => 'student number',
            'government_id' => 'government ID',
            'current_academic_year_id' => 'academic year',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate age appropriateness for grade level
            if ($this->date_of_birth && $this->current_grade_level) {
                $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
                
                if (!$this->isValidAgeForGrade($age, $this->current_grade_level)) {
                    $validator->errors()->add(
                        'date_of_birth',
                        'Student age is not appropriate for the selected grade level'
                    );
                }
            }

            // Ensure at least one primary emergency contact
            if ($this->emergency_contacts_json) {
                $hasPrimary = collect($this->emergency_contacts_json)
                    ->contains('is_primary', true);
                
                if (!$hasPrimary && count($this->emergency_contacts_json) > 1) {
                    $validator->errors()->add(
                        'emergency_contacts_json',
                        'One emergency contact must be designated as primary'
                    );
                }
            }

            // Validate family relationship primary contact uniqueness
            if ($this->family_relationships) {
                $primaryCount = collect($this->family_relationships)
                    ->where('primary_contact', true)
                    ->count();
                
                if ($primaryCount > 1) {
                    $validator->errors()->add(
                        'family_relationships',
                        'Only one family relationship can be designated as primary contact'
                    );
                }
            }
        });
    }

    /**
     * Check if age is appropriate for grade level.
     */
    protected function isValidAgeForGrade(int $age, string $gradeLevel): bool
    {
        $ageRanges = [
            'Pre-K' => [3, 5], 'K' => [4, 7],
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
}

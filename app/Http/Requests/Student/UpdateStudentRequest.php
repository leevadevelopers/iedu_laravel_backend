<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Student Request
 * 
 * Validates data for updating existing students with educational
 * business rules and change tracking validation.
 */
class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $student = $this->route('student');
        return auth('api')->user()->can('update', $student);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic Information (most fields are updatable)
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\'-]+$/'
            ],
            'last_name' => [
                'sometimes',
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
            
            // Personal Information (some restrictions on updates)
            'date_of_birth' => [
                'sometimes',
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
                // Unique rule accounting for current student
                Rule::unique('students')->ignore($this->route('student'))->where(function ($query) {
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
            'current_grade_level' => [
                'sometimes',
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
                'sometimes',
                'required',
                Rule::in(['enrolled', 'transferred', 'graduated', 'withdrawn', 'suspended'])
            ],
            'expected_graduation_date' => [
                'nullable',
                'date',
                'after:today'
            ],
            
            // Educational Profile Updates
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
            
            // Health & Safety Information Updates
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
            
            // Performance Indicators (usually updated by system, but can be manually adjusted)
            'current_gpa' => [
                'nullable',
                'numeric',
                'between:0,4.00'
            ],
            'attendance_rate' => [
                'nullable',
                'numeric',
                'between:0,100.00'
            ],
            'behavioral_points' => [
                'nullable',
                'integer',
                'min:-1000',
                'max:1000'
            ],
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
            'date_of_birth.before' => 'Date of birth must be in the past',
            'date_of_birth.after' => 'Student cannot be older than 25 years for K-12 enrollment',
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
            'current_academic_year_id.exists' => 'Selected academic year does not exist',
            
            // Performance Indicators
            'current_gpa.between' => 'GPA must be between 0.00 and 4.00',
            'attendance_rate.between' => 'Attendance rate must be between 0% and 100%',
            'behavioral_points.min' => 'Behavioral points cannot be less than -1000',
            'behavioral_points.max' => 'Behavioral points cannot be more than 1000',
            
            // Emergency Contacts Messages
            'emergency_contacts_json.min' => 'At least one emergency contact is required',
            'emergency_contacts_json.*.name.required' => 'Emergency contact name is required',
            'emergency_contacts_json.*.relationship.required' => 'Emergency contact relationship is required',
            'emergency_contacts_json.*.phone.required' => 'Emergency contact phone number is required',
            'emergency_contacts_json.*.email.email' => 'Emergency contact email must be valid',
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
            'current_grade_level' => 'grade level',
            'enrollment_status' => 'enrollment status',
            'expected_graduation_date' => 'expected graduation date',
            'current_academic_year_id' => 'academic year',
            'current_gpa' => 'GPA',
            'attendance_rate' => 'attendance rate',
            'behavioral_points' => 'behavioral points',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate age appropriateness for grade level if both are being updated
            if ($this->date_of_birth && $this->current_grade_level) {
                $age = \Carbon\Carbon::parse($this->date_of_birth)->age;
                
                if (!$this->isValidAgeForGrade($age, $this->current_grade_level)) {
                    $validator->errors()->add(
                        'date_of_birth',
                        'Student age is not appropriate for the selected grade level'
                    );
                }
            }

            // Validate grade level progression (cannot go backwards unless justified)
            if ($this->current_grade_level) {
                $currentStudent = $this->route('student');
                if (!$this->isValidGradeProgression($currentStudent->current_grade_level, $this->current_grade_level)) {
                    $validator->errors()->add(
                        'current_grade_level',
                        'Grade level progression appears invalid. Please verify the change is correct.'
                    );
                }
            }

            // Validate enrollment status transitions
            if ($this->enrollment_status) {
                $currentStudent = $this->route('student');
                if (!$this->isValidStatusTransition($currentStudent->enrollment_status, $this->enrollment_status)) {
                    $validator->errors()->add(
                        'enrollment_status',
                        'Invalid enrollment status transition'
                    );
                }
            }

            // Ensure at least one primary emergency contact if emergency contacts are provided
            if ($this->emergency_contacts_json && count($this->emergency_contacts_json) > 1) {
                $hasPrimary = collect($this->emergency_contacts_json)
                    ->contains('is_primary', true);
                
                if (!$hasPrimary) {
                    $validator->errors()->add(
                        'emergency_contacts_json',
                        'One emergency contact must be designated as primary'
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
            return true;
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

        if ($currentIndex === false || $newIndex === false) {
            return true; // Allow if either grade not in standard progression
        }

        // Allow progression forward or staying same, but warn on regression
        return $newIndex >= $currentIndex - 1; // Allow one grade back for retention
    }

    /**
     * Check if enrollment status transition is valid.
     */
    protected function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'enrolled' => ['enrolled', 'transferred', 'graduated', 'withdrawn', 'suspended'],
            'transferred' => ['enrolled'], // Can re-enroll
            'graduated' => [], // Final status
            'withdrawn' => ['enrolled'], // Can re-enroll
            'suspended' => ['enrolled', 'withdrawn'], // Can return or withdraw
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}

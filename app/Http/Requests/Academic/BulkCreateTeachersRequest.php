<?php

namespace App\Http\Requests\Academic;

class BulkCreateTeachersRequest extends BaseAcademicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'teachers' => 'required|array|min:1|max:50',
            'teachers.*.first_name' => 'required|string|max:100',
            'teachers.*.last_name' => 'required|string|max:100',
            'teachers.*.hire_date' => 'required|date|before_or_equal:today',
            'teachers.*.employment_type' => 'required|in:full_time,part_time,substitute,contract,volunteer',

            // Optional personal information
            'teachers.*.middle_name' => 'nullable|string|max:100',
            'teachers.*.preferred_name' => 'nullable|string|max:100',
            'teachers.*.title' => 'nullable|string|max:20|in:Mr.,Mrs.,Ms.,Dr.,Prof.,Rev.',
            'teachers.*.date_of_birth' => 'nullable|date|before:today',
            'teachers.*.gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'teachers.*.nationality' => 'nullable|string|max:50',
            'teachers.*.phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9\s\-\(\)]+$/'
            ],
            'teachers.*.email' => 'nullable|email|max:255',

            // Address information
            'teachers.*.address_json' => 'nullable|array',
            'teachers.*.address_json.street' => 'required_with:teachers.*.address_json|string|max:255',
            'teachers.*.address_json.city' => 'required_with:teachers.*.address_json|string|max:100',
            'teachers.*.address_json.state' => 'required_with:teachers.*.address_json|string|max:100',
            'teachers.*.address_json.postal_code' => 'required_with:teachers.*.address_json|string|max:20',
            'teachers.*.address_json.country' => 'required_with:teachers.*.address_json|string|max:100',

            // Professional information
            'teachers.*.termination_date' => 'nullable|date|after:teachers.*.hire_date',
            'teachers.*.status' => 'nullable|in:active,inactive,terminated,on_leave',
            'teachers.*.department' => 'nullable|string|max:100',
            'teachers.*.position' => 'nullable|string|max:100',
            'teachers.*.salary' => 'nullable|numeric|min:0|max:999999.99',

            // Educational background
            'teachers.*.education_json' => 'nullable|array',
            'teachers.*.education_json.*.degree' => 'required_with:teachers.*.education_json|string|max:100',
            'teachers.*.education_json.*.field' => 'required_with:teachers.*.education_json|string|max:100',
            'teachers.*.education_json.*.institution' => 'required_with:teachers.*.education_json|string|max:255',
            'teachers.*.education_json.*.year' => 'required_with:teachers.*.education_json|integer|min:1900|max:' . date('Y'),
            'teachers.*.education_json.*.gpa' => 'nullable|numeric|min:0|max:4.0',

            // Certifications
            'teachers.*.certifications_json' => 'nullable|array',
            'teachers.*.certifications_json.*.type' => 'required_with:teachers.*.certifications_json|string|max:100',
            'teachers.*.certifications_json.*.issuing_organization' => 'required_with:teachers.*.certifications_json|string|max:255',
            'teachers.*.certifications_json.*.issue_date' => 'required_with:teachers.*.certifications_json|date|before_or_equal:today',
            'teachers.*.certifications_json.*.expiry_date' => 'nullable|date|after:teachers.*.certifications_json.*.issue_date',
            'teachers.*.certifications_json.*.certification_number' => 'nullable|string|max:100',

            // Specializations
            'teachers.*.specializations_json' => 'nullable|array',
            'teachers.*.specializations_json.*' => 'string|max:100',

            // Schedule
            'teachers.*.schedule_json' => 'nullable|array',
            'teachers.*.schedule_json.*.day' => 'required_with:teachers.*.schedule_json|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'teachers.*.schedule_json.*.available_times' => 'required_with:teachers.*.schedule_json|array',
            'teachers.*.schedule_json.*.available_times.*' => [
                'string',
                'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
            ],

            // Emergency contacts
            'teachers.*.emergency_contacts_json' => 'nullable|array',
            'teachers.*.emergency_contacts_json.*.name' => 'required_with:teachers.*.emergency_contacts_json|string|max:100',
            'teachers.*.emergency_contacts_json.*.relationship' => 'required_with:teachers.*.emergency_contacts_json|string|max:50',
            'teachers.*.emergency_contacts_json.*.phone' => 'required_with:teachers.*.emergency_contacts_json|string|max:20|regex:/^[+]?[0-9\s\-()]+$/',
            'teachers.*.emergency_contacts_json.*.email' => 'nullable|email|max:255',
            'teachers.*.emergency_contacts_json.*.is_primary' => 'nullable|boolean',

            // Additional information
            'teachers.*.bio' => 'nullable|string|max:2000',
            'teachers.*.profile_photo_path' => 'nullable|string|max:500',
            'teachers.*.preferences_json' => 'nullable|array',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $teachers = $this->input('teachers', []);

            foreach ($teachers as $index => $teacher) {
                // Validate employee_id uniqueness if provided
                $employeeId = $teacher['employee_id'] ?? null;
                if ($employeeId) {
                    $schoolId = $this->getCurrentSchoolIdOrNull();
                    if ($schoolId) {
                        $exists = \App\Models\V1\Academic\Teacher::where('employee_id', $employeeId)
                            ->where('school_id', $schoolId)
                            ->exists();

                        if ($exists) {
                            $validator->errors()->add("teachers.{$index}.employee_id", 'This employee ID is already in use for this school.');
                        }
                    }
                }

                // Ensure at least one emergency contact is marked as primary
                $emergencyContacts = $teacher['emergency_contacts_json'] ?? [];
                if (!empty($emergencyContacts)) {
                    $hasPrimary = collect($emergencyContacts)->contains('is_primary', true);
                    if (!$hasPrimary) {
                        $validator->errors()->add("teachers.{$index}.emergency_contacts_json", 'At least one emergency contact must be marked as primary.');
                    }
                }

                // Validate that termination date is not in the future if status is terminated
                if (($teacher['status'] ?? '') === 'terminated' && !isset($teacher['termination_date'])) {
                    $validator->errors()->add("teachers.{$index}.termination_date", 'Termination date is required when status is terminated.');
                }

                // Validate that salary is provided for full-time and part-time employees
                $employmentType = $teacher['employment_type'] ?? '';
                if (in_array($employmentType, ['full_time', 'part_time']) && !isset($teacher['salary'])) {
                    $validator->errors()->add("teachers.{$index}.salary", 'Salary is required for ' . str_replace('_', ' ', $employmentType) . ' employees.');
                }
            }
        });
    }

    /**
     * Get custom messages for validation errors
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'teachers.required' => 'At least one teacher must be provided',
            'teachers.max' => 'Cannot create more than 50 teachers at once',
            'teachers.*.hire_date.before_or_equal' => 'Hire date cannot be in the future.',
            'teachers.*.termination_date.after' => 'Termination date must be after hire date.',
            'teachers.*.phone.regex' => 'The phone number format is invalid.',
            'teachers.*.schedule_json.*.available_times.*.regex' => 'Time format must be HH:MM (24-hour format).',
            'teachers.*.education_json.*.gpa.max' => 'GPA cannot exceed 4.0.',
            'teachers.*.certifications_json.*.expiry_date.after' => 'Expiry date must be after issue date.',
            'teachers.*.emergency_contacts_json.*.phone.regex' => 'The emergency contact phone number format is invalid.',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'teachers' => 'teachers list',
            'teachers.*.employee_id' => 'employee ID',
            'teachers.*.first_name' => 'first name',
            'teachers.*.last_name' => 'last name',
            'teachers.*.middle_name' => 'middle name',
            'teachers.*.preferred_name' => 'preferred name',
            'teachers.*.date_of_birth' => 'date of birth',
            'teachers.*.hire_date' => 'hire date',
            'teachers.*.termination_date' => 'termination date',
            'teachers.*.employment_type' => 'employment type',
            'teachers.*.address_json' => 'address',
            'teachers.*.education_json' => 'education',
            'teachers.*.certifications_json' => 'certifications',
            'teachers.*.specializations_json' => 'specializations',
            'teachers.*.schedule_json' => 'schedule',
            'teachers.*.emergency_contacts_json' => 'emergency contacts',
            'teachers.*.profile_photo_path' => 'profile photo',
            'teachers.*.preferences_json' => 'preferences',
        ]);
    }
}


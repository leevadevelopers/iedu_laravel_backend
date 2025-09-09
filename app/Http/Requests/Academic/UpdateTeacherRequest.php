<?php

namespace App\Http\Requests\Academic;

class UpdateTeacherRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $teacherId = $this->route('teacher') ? $this->route('teacher')->id : null;

        return [
            // Optional fields for updates
            'user_id' => 'nullable|exists:users,id',
            'employee_id' => [
                'nullable',
                'string',
                'max:50',
                'unique:teachers,employee_id,' . $teacherId . ',id,school_id,' . $this->getCurrentSchoolId()
            ],
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'preferred_name' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:20|in:Mr.,Mrs.,Ms.,Dr.,Prof.,Rev.',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20|regex:/^[\+]?[0-9\s\-\(\)]+$/',
            'email' => 'nullable|email|max:255',

            // Address information
            'address_json' => 'nullable|array',
            'address_json.street' => 'required_with:address_json|string|max:255',
            'address_json.city' => 'required_with:address_json|string|max:100',
            'address_json.state' => 'required_with:address_json|string|max:100',
            'address_json.postal_code' => 'required_with:address_json|string|max:20',
            'address_json.country' => 'required_with:address_json|string|max:100',

            // Professional information
            'employment_type' => 'nullable|in:full_time,part_time,substitute,contract,volunteer',
            'hire_date' => 'nullable|date|before_or_equal:today',
            'termination_date' => 'nullable|date|after:hire_date',
            'status' => 'nullable|in:active,inactive,terminated,on_leave',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0|max:999999.99',

            // Educational background
            'education_json' => 'nullable|array',
            'education_json.*.degree' => 'required_with:education_json|string|max:100',
            'education_json.*.field' => 'required_with:education_json|string|max:100',
            'education_json.*.institution' => 'required_with:education_json|string|max:255',
            'education_json.*.year' => 'required_with:education_json|integer|min:1900|max:' . date('Y'),
            'education_json.*.gpa' => 'nullable|numeric|min:0|max:4.0',

            // Certifications
            'certifications_json' => 'nullable|array',
            'certifications_json.*.type' => 'required_with:certifications_json|string|max:100',
            'certifications_json.*.issuing_organization' => 'required_with:certifications_json|string|max:255',
            'certifications_json.*.issue_date' => 'required_with:certifications_json|date|before_or_equal:today',
            'certifications_json.*.expiry_date' => 'nullable|date|after:certifications_json.*.issue_date',
            'certifications_json.*.certification_number' => 'nullable|string|max:100',

            // Specializations
            'specializations_json' => 'nullable|array',
            'specializations_json.*' => 'string|max:100',

            // Schedule
            'schedule_json' => 'nullable|array',
            'schedule_json.*.day' => 'required_with:schedule_json|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule_json.*.available_times' => 'required_with:schedule_json|array',
            'schedule_json.*.available_times.*' => 'string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',

            // Emergency contacts
            'emergency_contacts_json' => 'nullable|array',
            'emergency_contacts_json.*.name' => 'required_with:emergency_contacts_json|string|max:100',
            'emergency_contacts_json.*.relationship' => 'required_with:emergency_contacts_json|string|max:50',
            'emergency_contacts_json.*.phone' => 'required_with:emergency_contacts_json|string|max:20|regex:/^[\+]?[0-9\s\-\(\)]+$/',
            'emergency_contacts_json.*.email' => 'nullable|email|max:255',
            'emergency_contacts_json.*.is_primary' => 'nullable|boolean',

            // Additional information
            'bio' => 'nullable|string|max:2000',
            'profile_photo_path' => 'nullable|string|max:500',
            'preferences_json' => 'nullable|array',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get the current teacher record
            $teacher = $this->route('teacher');
            if (!$teacher) {
                return;
            }

            // Ensure at least one emergency contact is marked as primary if emergency contacts are provided
            $emergencyContacts = $this->input('emergency_contacts_json', []);
            if (!empty($emergencyContacts)) {
                $hasPrimary = collect($emergencyContacts)->contains('is_primary', true);
                if (!$hasPrimary) {
                    $validator->errors()->add('emergency_contacts_json', 'At least one emergency contact must be marked as primary.');
                }
            }

            // Validate that termination date is not in the future if status is being changed to terminated
            if ($this->input('status') === 'terminated' && !$this->filled('termination_date')) {
                // If no termination date provided and status is being set to terminated, require it
                if (!$teacher->termination_date) {
                    $validator->errors()->add('termination_date', 'Termination date is required when status is terminated.');
                }
            }

            // Validate that salary is provided for full-time and part-time employees
            $employmentType = $this->input('employment_type', $teacher->employment_type);
            if (in_array($employmentType, ['full_time', 'part_time']) && !$this->filled('salary') && !$teacher->salary) {
                $validator->errors()->add('salary', 'Salary is required for ' . str_replace('_', ' ', $employmentType) . ' employees.');
            }

            // Validate hire_date vs termination_date relationship
            $hireDate = $this->input('hire_date', $teacher->hire_date);
            $terminationDate = $this->input('termination_date');

            if ($hireDate && $terminationDate && $terminationDate <= $hireDate) {
                $validator->errors()->add('termination_date', 'Termination date must be after hire date.');
            }

            // Validate that employee_id is unique within the school
            $employeeId = $this->input('employee_id');
            if ($employeeId && $employeeId !== $teacher->employee_id) {
                $existingTeacher = \App\Models\V1\Academic\Teacher::where('school_id', $this->getCurrentSchoolId())
                    ->where('employee_id', $employeeId)
                    ->where('id', '!=', $teacher->id)
                    ->first();

                if ($existingTeacher) {
                    $validator->errors()->add('employee_id', 'This employee ID is already in use for this school.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Ensure employee_id is uppercase for consistency if provided
        if ($this->filled('employee_id')) {
            $this->merge(['employee_id' => strtoupper($this->employee_id)]);
        }

        // If updating status to terminated and no termination date provided, set it to today
        if ($this->input('status') === 'terminated' && !$this->filled('termination_date')) {
            $teacher = $this->route('teacher');
            if ($teacher && !$teacher->termination_date) {
                $this->merge(['termination_date' => now()->toDateString()]);
            }
        }

        // If updating status to active and termination date is provided, clear it
        if ($this->input('status') === 'active' && $this->filled('termination_date')) {
            $this->merge(['termination_date' => null]);
        }
    }

    /**
     * Get custom messages for validation errors
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'employee_id.unique' => 'This employee ID is already in use for this school.',
            'hire_date.before_or_equal' => 'Hire date cannot be in the future.',
            'termination_date.after' => 'Termination date must be after hire date.',
            'phone.regex' => 'The phone number format is invalid.',
            'schedule_json.*.available_times.*.regex' => 'Time format must be HH:MM (24-hour format).',
            'education_json.*.gpa.max' => 'GPA cannot exceed 4.0.',
            'certifications_json.*.expiry_date.after' => 'Expiry date must be after issue date.',
            'emergency_contacts_json.*.phone.regex' => 'The emergency contact phone number format is invalid.',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'employee_id' => 'employee ID',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'middle_name' => 'middle name',
            'preferred_name' => 'preferred name',
            'date_of_birth' => 'date of birth',
            'hire_date' => 'hire date',
            'termination_date' => 'termination date',
            'employment_type' => 'employment type',
            'address_json' => 'address',
            'education_json' => 'education',
            'certifications_json' => 'certifications',
            'specializations_json' => 'specializations',
            'schedule_json' => 'schedule',
            'emergency_contacts_json' => 'emergency contacts',
            'profile_photo_path' => 'profile photo',
            'preferences_json' => 'preferences',
        ]);
    }
}

<?php

namespace App\Http\Requests\Academic;

class StoreAcademicYearRequest extends BaseAcademicRequest
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
            'name' => 'required|string|max:100',
            'code' => [
                'required',
                'string',
                'max:20',
                'unique:academic_years,code,NULL,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'year' => 'required|string|max:10',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:end_date',
            'registration_deadline' => 'nullable|date|before:start_date',
            'term_structure' => 'required|in:semesters,trimesters,quarters,year_round',
            'total_terms' => 'nullable|integer|min:1|max:4',
            'total_instructional_days' => 'nullable|integer|min:160|max:220',
            'holidays_json' => 'nullable|array',
            'status' => 'nullable|in:planning,active,completed,archived',
            'is_current' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'school_id' => 'required|exists:schools,id',
            'tenant_id' => 'required|exists:tenants,id'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);

                // Check minimum duration
                if ($endDate->diffInDays($startDate) < 180) {
                    $validator->errors()->add('end_date', 'Academic year must be at least 180 days long.');
                }

                // Check maximum duration
                if ($endDate->diffInDays($startDate) > 400) {
                    $validator->errors()->add('end_date', 'Academic year cannot exceed 400 days.');
                }
            }

            // Validate enrollment dates relationship
            if ($this->filled('enrollment_start_date') && $this->filled('enrollment_end_date')) {
                $enrollmentStart = \Carbon\Carbon::parse($this->enrollment_start_date);
                $enrollmentEnd = \Carbon\Carbon::parse($this->enrollment_end_date);

                if ($enrollmentEnd->lte($enrollmentStart)) {
                    $validator->errors()->add('enrollment_end_date', 'Enrollment end date must be after enrollment start date.');
                }
            }

            // Validate enrollment_end_date is before academic year end_date
            if ($this->filled('enrollment_end_date') && $this->filled('end_date')) {
                $enrollmentEnd = \Carbon\Carbon::parse($this->enrollment_end_date);
                $academicYearEnd = \Carbon\Carbon::parse($this->end_date);

                if ($enrollmentEnd->gte($academicYearEnd)) {
                    $validator->errors()->add('enrollment_end_date', 'Enrollment end date must be before the academic year end date.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default total_terms based on term_structure
        if ($this->filled('term_structure') && !$this->filled('total_terms')) {
            $defaultTerms = [
                'semesters' => 2,
                'trimesters' => 3,
                'quarters' => 4,
                'year_round' => 4
            ];

            $this->merge([
                'total_terms' => $defaultTerms[$this->term_structure] ?? 2
            ]);
        }
    }
}

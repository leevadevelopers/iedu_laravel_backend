<?php

namespace App\Http\Requests\Academic;

class StoreSubjectRequest extends BaseAcademicRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'subject_area' => [
                'required',
                'in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other'
            ],
            'grade_levels' => 'required|array|min:1',
            'grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'learning_standards_json' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'integer|exists:subjects,id',
            'credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'is_core_subject' => 'nullable|boolean',
            'is_elective' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,archived'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure subject is either core or elective, but not both
            if ($this->boolean('is_core_subject') && $this->boolean('is_elective')) {
                $validator->errors()->add('is_elective', 'A subject cannot be both core and elective.');
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Add tenant_id from authenticated user
        $this->merge([
            'tenant_id' => auth()->user()->tenant_id
        ]);

        // Set default credit hours based on subject area
        if (!$this->filled('credit_hours')) {
            $defaultCredits = [
                'mathematics' => 1.0,
                'science' => 1.0,
                'language_arts' => 1.0,
                'social_studies' => 1.0,
                'foreign_language' => 1.0,
                'arts' => 0.5,
                'physical_education' => 0.5,
                'technology' => 0.5,
                'vocational' => 1.0,
                'other' => 0.5
            ];

            $this->merge([
                'credit_hours' => $defaultCredits[$this->subject_area] ?? 1.0
            ]);
        }
    }
}

<?php

namespace App\Http\Requests\Academic;

class BulkCreateSubjectsRequest extends BaseAcademicRequest
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
            'subjects' => 'required|array|min:1|max:50',
            'subjects.*.school_id' => 'required|integer|exists:schools,id',
            'subjects.*.name' => 'required|string|max:255',
            'subjects.*.code' => [
                'required',
                'string',
                'max:50'
            ],
            'subjects.*.description' => 'nullable|string|max:1000',
            'subjects.*.subject_area' => [
                'required',
                'in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other'
            ],
            'subjects.*.grade_levels' => 'required|array|min:1',
            'subjects.*.grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'subjects.*.learning_standards_json' => 'nullable|array',
            'subjects.*.prerequisites' => 'nullable|array',
            'subjects.*.prerequisites.*' => 'integer|exists:subjects,id',
            'subjects.*.credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'subjects.*.is_core_subject' => 'nullable|boolean',
            'subjects.*.is_elective' => 'nullable|boolean',
            'subjects.*.status' => 'nullable|in:active,inactive,archived'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $subjects = $this->input('subjects', []);

            foreach ($subjects as $index => $subject) {
                // Ensure subject is either core or elective, but not both
                if (($subject['is_core_subject'] ?? false) && ($subject['is_elective'] ?? false)) {
                    $validator->errors()->add("subjects.{$index}.is_elective", 'A subject cannot be both core and elective.');
                }

                // Validate subject code uniqueness within school
                if (isset($subject['code']) && isset($subject['school_id'])) {
                    $schoolId = $subject['school_id'];
                    $exists = \App\Models\V1\Academic\Subject::where('code', $subject['code'])
                        ->where('school_id', $schoolId)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add("subjects.{$index}.code", 'This subject code is already in use for this school.');
                    }
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
            'subjects.required' => 'At least one subject must be provided',
            'subjects.max' => 'Cannot create more than 50 subjects at once',
            'subjects.*.name.required' => 'Subject name is required',
            'subjects.*.code.required' => 'Subject code is required',
            'subjects.*.subject_area.required' => 'Subject area is required',
            'subjects.*.grade_levels.required' => 'At least one grade level is required',
        ]);
    }

    /**
     * Get custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'subjects' => 'subjects list',
            'subjects.*.name' => 'subject name',
            'subjects.*.code' => 'subject code',
            'subjects.*.subject_area' => 'subject area',
            'subjects.*.grade_levels' => 'grade levels',
            'subjects.*.is_core_subject' => 'core subject',
            'subjects.*.is_elective' => 'elective',
        ]);
    }
}


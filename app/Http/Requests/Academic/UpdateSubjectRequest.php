<?php

namespace App\Http\Requests\Academic;

class UpdateSubjectRequest extends BaseAcademicRequest
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
        $subjectId = $this->route('id');
        $currentSchoolId = $this->getCurrentSchoolIdOrNull();

        return [
            'school_id' => 'sometimes|required|integer|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'unique:subjects,code,' . $subjectId . ',id,school_id,' . $this->input('school_id', $currentSchoolId)
            ],
            'description' => 'nullable|string|max:1000',
            'subject_area' => [
                'sometimes',
                'required',
                'in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other'
            ],
            'grade_levels' => 'sometimes|required|array|min:1',
            'grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'learning_standards_json' => 'nullable|array',
            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'integer|exists:subjects,id',
            'credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'is_core_subject' => 'nullable|boolean',
            'is_elective' => 'nullable|boolean',
            'status' => 'sometimes|in:active,inactive,archived'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_core_subject') && $this->boolean('is_elective')) {
                $validator->errors()->add('is_elective', 'A subject cannot be both core and elective.');
            }
        });
    }
}

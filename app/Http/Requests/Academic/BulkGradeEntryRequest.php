<?php

namespace App\Http\Requests\Academic;

class BulkGradeEntryRequest extends BaseAcademicRequest
{
    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        return [
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'required',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'assessment_name' => 'required|string|max:255',
            'assessment_type' => 'required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'required|date|before_or_equal:today',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:10',
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => [
                'required',
                'integer',
                'exists:students,id,school_id,' . $this->getCurrentSchoolId() . ',enrollment_status,enrolled'
            ],
            'grades.*.raw_score' => 'nullable|numeric|min:0',
            'grades.*.percentage_score' => 'nullable|numeric|min:0|max:100',
            'grades.*.letter_grade' => 'nullable|string|max:5',
            'grades.*.points_earned' => 'nullable|numeric|min:0',
            'grades.*.points_possible' => 'nullable|numeric|min:0.1',
            'grades.*.teacher_comments' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('grades')) {
                foreach ($this->grades as $index => $grade) {
                    // At least one score must be provided
                    if (!isset($grade['raw_score']) && !isset($grade['percentage_score']) &&
                        !isset($grade['points_earned']) && !isset($grade['letter_grade'])) {
                        $validator->errors()->add("grades.{$index}.raw_score",
                            'At least one score value must be provided.');
                    }

                    // Points validation
                    if (isset($grade['points_earned']) && isset($grade['points_possible']) &&
                        $grade['points_earned'] > $grade['points_possible']) {
                        $validator->errors()->add("grades.{$index}.points_earned",
                            'Points earned cannot exceed points possible.');
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('weight')) {
            $this->merge(['weight' => 1.0]);
        }
    }
}

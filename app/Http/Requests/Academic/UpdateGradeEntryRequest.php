<?php

namespace App\Http\Requests\Academic;

class UpdateGradeEntryRequest extends BaseAcademicRequest
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
            'assessment_name' => 'sometimes|required|string|max:255',
            'assessment_type' => 'sometimes|required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'sometimes|required|date|before_or_equal:today',
            'raw_score' => 'nullable|numeric|min:0',
            'percentage_score' => 'nullable|numeric|min:0|max:100',
            'letter_grade' => 'nullable|string|max:5',
            'points_earned' => 'nullable|numeric|min:0',
            'points_possible' => 'nullable|numeric|min:0.1',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0|max:10',
            'teacher_comments' => 'nullable|string|max:1000',
            'private_notes' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Points earned cannot exceed points possible
            if ($this->filled('points_earned') && $this->filled('points_possible') &&
                $this->points_earned > $this->points_possible) {
                $validator->errors()->add('points_earned', 'Points earned cannot exceed points possible.');
            }
        });
    }
}

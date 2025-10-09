<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'assessments_count' => 'sometimes|integer|min:1|max:20',
            'default_passing_score' => 'sometimes|numeric|min:0|max:100',
            'rounding_policy' => 'sometimes|in:none,up,down,nearest',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'allow_grade_review' => 'nullable|boolean',
            'review_deadline_days' => 'nullable|integer|min:1|max:365',
            'config' => 'nullable|array',
        ];
    }
}


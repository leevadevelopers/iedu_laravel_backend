<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'assessments_count' => 'required|integer|min:1|max:20',
            'default_passing_score' => 'required|numeric|min:0|max:100',
            'rounding_policy' => 'required|in:none,up,down,nearest',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'allow_grade_review' => 'nullable|boolean',
            'review_deadline_days' => 'nullable|integer|min:1|max:365',
            'config' => 'nullable|array',
        ];
    }
}


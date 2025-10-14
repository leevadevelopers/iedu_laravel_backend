<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:pending,in_review,accepted,rejected,resolved',
            'reviewer_comments' => 'nullable|string',
            'revised_marks' => 'nullable|numeric|min:0',
        ];
    }
}


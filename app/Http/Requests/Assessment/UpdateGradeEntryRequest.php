<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points_earned' => 'nullable|numeric|min:0',
            'points_possible' => 'nullable|numeric|min:0',
            'percentage_score' => 'nullable|numeric|min:0|max:100',
            'letter_grade' => 'nullable|string|max:5',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'teacher_comments' => 'nullable|string',
            'private_notes' => 'nullable|string',
            'reason' => 'sometimes|string', // Para auditoria
        ];
    }
}


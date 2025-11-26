<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_weight' => 'nullable|numeric|min:0|max:100',
            'max_score' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean',
        ];
    }
}


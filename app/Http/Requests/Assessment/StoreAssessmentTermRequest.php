<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_published' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }
}


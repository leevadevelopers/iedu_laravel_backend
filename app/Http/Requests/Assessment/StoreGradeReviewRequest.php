<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Alunos e pais podem criar pedidos de revisão
        // return in_array($this->user()->getRoleNames()->first(), ['student', 'parent']);
        return true;

    }

    public function rules(): array
    {
        return [
            'grade_entry_id' => 'required|exists:grade_entries,id',
            'reason' => 'required|string|min:10',
            'details' => 'nullable|string',
        ];
    }
}


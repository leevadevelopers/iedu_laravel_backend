<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'assessment_name' => 'required|string|max:255',
            'assessment_type' => 'required|in:formative,summative,project,participation,homework,quiz,exam',
            'assessment_date' => 'required|date',
            'points_earned' => 'nullable|numeric|min:0',
            'points_possible' => 'nullable|numeric|min:0',
            'percentage_score' => 'nullable|numeric|min:0|max:100',
            'letter_grade' => 'nullable|string|max:5',
            'grade_category' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'teacher_comments' => 'nullable|string',
            'private_notes' => 'nullable|string',
        ];
    }
}


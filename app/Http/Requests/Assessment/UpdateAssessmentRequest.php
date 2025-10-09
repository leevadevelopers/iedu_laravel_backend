<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term_id' => 'sometimes|exists:assessment_terms,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'class_id' => 'sometimes|exists:classes,id',
            'teacher_id' => 'nullable|exists:users,id',
            'type_id' => 'sometimes|exists:assessment_types,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'submission_deadline' => 'nullable|date',
            'total_marks' => 'sometimes|numeric|min:0',
            'weight' => 'nullable|numeric|min:0|max:100',
            'visibility' => 'nullable|in:public,private,tenant',
            'allow_upload_submissions' => 'nullable|boolean',
            'status' => 'nullable|in:draft,scheduled,in_progress,completed,cancelled',
            'metadata' => 'nullable|array',
        ];
    }
}


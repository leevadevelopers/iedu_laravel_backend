<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term_id' => 'required|exists:assessment_terms,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'nullable|exists:users,id',
            'type_id' => 'required|exists:assessment_types,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'submission_deadline' => 'nullable|date',
            'total_marks' => 'required|numeric|min:0',
            'weight' => 'nullable|numeric|min:0|max:100',
            'visibility' => 'nullable|in:public,private,tenant',
            'allow_upload_submissions' => 'nullable|boolean',
            'status' => 'nullable|in:draft,scheduled,in_progress,completed,cancelled',
            'metadata' => 'nullable|array',
            'components' => 'nullable|array',
            'components.*.name' => 'required|string|max:255',
            'components.*.description' => 'nullable|string',
            'components.*.weight_pct' => 'required|numeric|min:0|max:100',
            'components.*.max_marks' => 'required|numeric|min:0',
            'components.*.rubric' => 'nullable|array',
        ];
    }
}


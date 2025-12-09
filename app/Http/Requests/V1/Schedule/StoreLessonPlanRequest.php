<?php

namespace App\Http\Requests\V1\Schedule;

class StoreLessonPlanRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'week_start' => 'required|date',
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'visibility' => 'nullable|in:private,shared,library',
            'status' => 'nullable|in:draft,published,archived',
            'day_blocks' => 'nullable|array',
            'objectives' => 'nullable|array',
            'materials' => 'nullable|array',
            'activities' => 'nullable|array',
            'assessment_links' => 'nullable|array',
            'tags' => 'nullable|array',
            'homework' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'share_with_classes' => 'nullable|array',
            'share_with_classes.*' => 'integer|exists:classes,id,school_id,' . $this->getCurrentSchoolId(),
        ];
    }
}


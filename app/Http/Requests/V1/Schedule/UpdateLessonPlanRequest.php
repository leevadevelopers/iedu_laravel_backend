<?php

namespace App\Http\Requests\V1\Schedule;

class UpdateLessonPlanRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'week_start' => 'sometimes|required|date',
            'class_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'subject_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'teacher_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId(),
            ],
            'visibility' => 'sometimes|in:private,shared,library',
            'status' => 'sometimes|in:draft,published,archived',
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
            'lesson_id' => 'nullable|integer|exists:lessons,id',
        ];
    }
}


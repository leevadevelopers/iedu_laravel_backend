<?php

namespace App\Http\Requests\V1\Schedule;

class ShareLessonPlanRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visibility' => 'required|in:private,shared,library',
            'share_with_classes' => 'nullable|array',
            'share_with_classes.*' => 'integer|exists:classes,id,school_id,' . $this->getCurrentSchoolId(),
        ];
    }
}


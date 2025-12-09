<?php

namespace App\Http\Requests\V1\Schedule;

class PublishLessonPlanRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'objectives' => 'nullable|array',
            'day_blocks' => 'nullable|array',
            'materials' => 'nullable|array',
            'activities' => 'nullable|array',
            'assessment_links' => 'nullable|array',
            'tags' => 'nullable|array',
            'homework' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasObjectives = $this->filled('objectives') && is_array($this->objectives) && count($this->objectives) > 0;
            $hasDayBlocks = $this->filled('day_blocks') && is_array($this->day_blocks) && count($this->day_blocks) > 0;

            if (!$hasObjectives && !$hasDayBlocks) {
                $validator->errors()->add('objectives', 'Informe objetivos ou blocos de aula antes de publicar.');
            }
        });
    }
}


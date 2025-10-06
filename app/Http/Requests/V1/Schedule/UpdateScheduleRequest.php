<?php

namespace App\Http\Requests\V1\Schedule;

class UpdateScheduleRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations
            'subject_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'class_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'teacher_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'classroom' => 'nullable|string|max:50',

            // Timing
            'period' => 'sometimes|required|in:morning,afternoon,evening,night',
            'day_of_week' => 'sometimes|required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'sometimes|required|date|before:end_date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'sometimes|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Same validations as StoreScheduleRequest
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 30) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 30 minutos.');
                }
            }

            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }
        });
    }
}

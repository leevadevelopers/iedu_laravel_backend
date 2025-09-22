<?php

namespace App\Http\Requests\V1\Schedule;

class StoreScheduleRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
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
            'period' => 'required|in:morning,afternoon,evening,night',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'nullable|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array',

            // Auto-generation
            'auto_generate_lessons' => 'boolean'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate minimum duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 30) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 30 minutos.');
                }

                if ($duration > 240) { // 4 hours
                    $validator->errors()->add('end_time', 'A duração máxima da aula deve ser de 4 horas.');
                }
            }

            // Validate date range
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);
                $daysDiff = $endDate->diffInDays($startDate);

                if ($daysDiff > 365) {
                    $validator->errors()->add('end_date', 'O período do horário não pode exceder 1 ano.');
                }
            }

            // Validate online meeting URL if online
            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }
        });
    }
}

<?php

namespace App\Http\Requests\V1\Schedule;

class StoreLessonRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            'schedule_id' => [
                'nullable',
                'integer',
                'exists:schedules,id,school_id,' . $this->getCurrentSchoolId()
            ],

            // Basic info
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'objectives' => 'nullable|array',
            'objectives.*' => 'string|max:500',

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
            'academic_term_id' => [
                'required',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],

            // Timing
            'lesson_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',

            // Location and format
            'classroom' => 'nullable|string|max:50',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'online_meeting_details' => 'nullable|array',

            // Type and status
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,postponed,absent_teacher',
            'type' => 'nullable|in:regular,makeup,extra,review,exam,practical,field_trip',

            // Content
            'content_summary' => 'nullable|string|max:2000',
            'curriculum_topics' => 'nullable|array',
            'homework_assigned' => 'nullable|string|max:1000',
            'homework_due_date' => 'nullable|date|after:lesson_date',

            // Teacher notes
            'teacher_notes' => 'nullable|string|max:1000',
            'lesson_observations' => 'nullable|string|max:1000',
            'student_participation' => 'nullable|array',

            // Approval
            'requires_approval' => 'boolean'
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

                if ($duration < 15) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 15 minutos.');
                }
            }

            // Validate online meeting requirements
            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }

            // Validate homework due date
            if ($this->filled('homework_assigned') && $this->filled('homework_due_date')) {
                $lessonDate = \Carbon\Carbon::parse($this->lesson_date);
                $dueDate = \Carbon\Carbon::parse($this->homework_due_date);

                if ($dueDate->lte($lessonDate)) {
                    $validator->errors()->add('homework_due_date', 'A data de entrega deve ser posterior à data da aula.');
                }
            }
        });
    }
}

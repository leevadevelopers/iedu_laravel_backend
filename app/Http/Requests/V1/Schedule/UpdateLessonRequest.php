<?php

namespace App\Http\Requests\V1\Schedule;

class UpdateLessonRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'objectives' => 'nullable|array',

            // Timing (restricted if lesson is in progress or completed)
            'lesson_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'duration_minutes' => 'nullable|integer|min:15|max:480', // 15 min to 8 hours

            // Location and format
            'classroom' => 'nullable|string|max:50',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'online_meeting_details' => 'nullable|array',

            // Type and status
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled,postponed,absent_teacher',
            'type' => 'sometimes|in:regular,makeup,extra,review,exam,practical,field_trip',

            // Content
            'content_summary' => 'nullable|string|max:2000',
            'curriculum_topics' => 'nullable|array',
            'homework_assigned' => 'nullable|string|max:1000',
            'homework_due_date' => 'nullable|date|after:lesson_date',

            // Teacher notes
            'teacher_notes' => 'nullable|string|max:1000',
            'lesson_observations' => 'nullable|string|max:1000',
            'student_participation' => 'nullable|array'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lesson = $this->route('lesson');

            // Restrict timing changes for completed lessons
            if ($lesson && $lesson->status === 'completed') {
                if ($this->filled('lesson_date') || $this->filled('start_time') || $this->filled('end_time')) {
                    $validator->errors()->add('lesson_date', 'Não é possível alterar horários de aulas já concluídas.');
                }
            }

            // Validate duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 15) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 15 minutos.');
                }
            }
        });
    }
}

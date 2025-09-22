<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class LessonResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,

            // Basic info
            'title' => $this->title,
            'description' => $this->description,
            'objectives' => $this->objectives,

            // Associations
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'academic_term_id' => $this->academic_term_id,

            // Timing
            'lesson_date' => $this->formatDate($this->lesson_date),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'formatted_time' => $this->formatted_time,
            'duration_minutes' => $this->duration_minutes,

            // Location and format
            'classroom' => $this->classroom,
            'is_online' => $this->is_online,
            'online_meeting_url' => $this->when($this->is_online, $this->online_meeting_url),
            'online_meeting_details' => $this->when($this->is_online, $this->online_meeting_details),

            // Status and type
            'status' => $this->status,
            'status_label' => $this->status_label,
            'type' => $this->type,
            'type_label' => $this->type_label,

            // Content and curriculum
            'content_summary' => $this->content_summary,
            'curriculum_topics' => $this->curriculum_topics,
            'homework_assigned' => $this->homework_assigned,
            'homework_due_date' => $this->formatDate($this->homework_due_date),

            // Attendance
            'expected_students' => $this->expected_students,
            'present_students' => $this->present_students,
            'attendance_rate' => $this->attendance_rate,

            // Teacher notes
            'teacher_notes' => $this->teacher_notes,
            'lesson_observations' => $this->lesson_observations,
            'student_participation' => $this->student_participation,

            // Flags
            'is_today' => $this->isToday(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'has_homework' => $this->hasHomework(),
            'has_contents' => $this->hasContents(),

            // Approval
            'requires_approval' => $this->requires_approval,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->formatDateTime($this->approved_at),

            // Relationships
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code
                ];
            }),

            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                    'grade_level' => $this->class->grade_level,
                    'current_enrollment' => $this->class->current_enrollment
                ];
            }),

            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'full_name' => $this->teacher->full_name,
                    'display_name' => $this->teacher->display_name
                ];
            }),

            'schedule' => $this->whenLoaded('schedule', function () {
                return [
                    'id' => $this->schedule->id,
                    'name' => $this->schedule->name,
                    'day_of_week' => $this->schedule->day_of_week,
                    'period' => $this->schedule->period
                ];
            }),

            'contents' => $this->whenLoaded('contents', LessonContentResource::collection($this->contents)),

            'attendances_summary' => $this->whenLoaded('attendances', function () {
                return [
                    'total' => $this->attendances->count(),
                    'present' => $this->attendances->where('status', 'present')->count(),
                    'absent' => $this->attendances->where('status', 'absent')->count(),
                    'late' => $this->attendances->where('status', 'late')->count(),
                    'excused' => $this->attendances->where('status', 'excused')->count()
                ];
            }),

            // Audit
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ]);
    }
}

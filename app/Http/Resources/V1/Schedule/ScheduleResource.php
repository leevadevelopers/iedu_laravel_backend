<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class ScheduleResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            // Associations
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_term_id' => $this->academic_term_id,
            'classroom' => $this->classroom,

            // Timing
            'period' => $this->period,
            'period_label' => $this->period_label,
            'day_of_week' => $this->day_of_week,
            'day_of_week_label' => $this->day_of_week_label,
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'formatted_time' => $this->formatted_time,
            'duration_in_minutes' => $this->duration_in_minutes,

            // Date range
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'recurrence_pattern' => $this->recurrence_pattern,

            // Configuration
            'status' => $this->status,
            'is_online' => $this->is_online,
            'online_meeting_url' => $this->when($this->is_online, $this->online_meeting_url),
            'configuration_json' => $this->configuration_json,

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
                    'grade_level' => $this->class->grade_level
                ];
            }),

            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'full_name' => $this->teacher->full_name,
                    'display_name' => $this->teacher->display_name
                ];
            }),

            'academic_year' => $this->whenLoaded('academicYear', function () {
                return [
                    'id' => $this->academicYear->id,
                    'name' => $this->academicYear->name
                ];
            }),

            'lessons_count' => $this->whenLoaded('lessons', $this->lessons->count()),

            // Audit
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ]);
    }
}

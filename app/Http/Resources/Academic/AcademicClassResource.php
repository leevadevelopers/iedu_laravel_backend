<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicClassResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_term_id' => $this->academic_term_id,
            'name' => $this->name,
            'section' => $this->section,
            'class_code' => $this->class_code,
            'grade_level' => $this->grade_level,
            'max_students' => $this->max_students,
            'current_enrollment' => $this->current_enrollment,
            'primary_teacher_id' => $this->primary_teacher_id,
            'additional_teachers_json' => $this->additional_teachers_json,
            'schedule_json' => $this->schedule_json,
            'room_number' => $this->room_number,
            'status' => $this->status,

            // Calculated fields
            'enrollment_percentage' => $this->formatDecimal($this->getEnrollmentPercentage()),
            'available_seats' => $this->getAvailableSeats(),
            'has_available_seats' => $this->hasAvailableSeats(),
            'display_name' => $this->name . ($this->section ? ' - ' . $this->section : ''),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'subject' => $this->whenLoaded('subject', new SubjectResource($this->subject)),
            'academic_year' => $this->whenLoaded('academicYear', new AcademicYearResource($this->academicYear)),
            'academic_term' => $this->whenLoaded('academicTerm', new AcademicTermResource($this->academicTerm)),
            'primary_teacher' => $this->whenLoaded('primaryTeacher', new TeacherResource($this->primaryTeacher)),
            'students' => $this->whenLoaded('students', StudentResource::collection($this->students)),
            'grade_entries' => $this->whenLoaded('gradeEntries', GradeEntryResource::collection($this->gradeEntries)),

            // Schedule information
            'schedule' => $this->when(
                $this->schedule_json,
                function () {
                    return collect($this->schedule_json)->map(function ($schedule) {
                        return [
                            'day' => $schedule['day'] ?? null,
                            'start_time' => $schedule['start_time'] ?? null,
                            'end_time' => $schedule['end_time'] ?? null,
                            'room' => $schedule['room'] ?? $this->room_number,
                        ];
                    });
                }
            ),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeEntries') || $this->resource->relationLoaded('students'),
                function () {
                    $stats = [];

                    if ($this->resource->relationLoaded('gradeEntries')) {
                        $gradeEntries = $this->gradeEntries;
                        $stats['grade_entries_count'] = $gradeEntries->count();
                        $stats['average_grade'] = $gradeEntries->isNotEmpty()
                            ? $this->formatDecimal($gradeEntries->avg('percentage_score'))
                            : null;
                    }

                    if ($this->resource->relationLoaded('students')) {
                        $stats['enrolled_students_count'] = $this->students->count();
                    }

                    return $stats;
                }
            ),
        ]);
    }
}

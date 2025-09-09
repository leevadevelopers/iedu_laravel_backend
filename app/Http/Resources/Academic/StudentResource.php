<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class StudentResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'display_name' => $this->preferred_name ?: $this->first_name,
            'current_grade_level' => $this->current_grade_level,
            'enrollment_status' => $this->enrollment_status,
            'current_gpa' => $this->formatDecimal($this->current_gpa),
            'attendance_rate' => $this->formatDecimal($this->attendance_rate),
            'behavioral_points' => $this->behavioral_points,

            // Only include sensitive information for authorized users
            'email' => $this->when(auth()->user()->user_type !== 'student', $this->email),
            'phone' => $this->when(auth()->user()->user_type !== 'student', $this->phone),
            'date_of_birth' => $this->when(auth()->user()->user_type !== 'student', $this->formatDate($this->date_of_birth)),
        ]);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentEnrollmentHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Derive enrollment_status from withdrawal_date
        // If withdrawal_date is null, enrollment is active; otherwise it's inactive/transferred
        $enrollmentStatus = $this->withdrawal_date ? 'inactive' : 'active';
        
        // If withdrawal_type is 'graduation', status should be 'graduated'
        if ($this->withdrawal_type === 'graduation') {
            $enrollmentStatus = 'graduated';
        } elseif ($this->withdrawal_type === 'transfer_out') {
            $enrollmentStatus = 'transferred';
        }

        // Format student data
        $studentData = null;
        if ($this->relationLoaded('student') && $this->student) {
            $studentData = [
                'id' => $this->student->id,
                'name' => trim(($this->student->first_name ?? '') . ' ' . ($this->student->last_name ?? '')),
                'full_name' => trim(($this->student->first_name ?? '') . ' ' . ($this->student->last_name ?? '')),
                'first_name' => $this->student->first_name,
                'last_name' => $this->student->last_name,
                'student_number' => $this->student->student_number,
                'current_grade_level' => $this->student->current_grade_level,
            ];
        }

        // Format school data
        $schoolData = null;
        if ($this->relationLoaded('school') && $this->school) {
            $schoolData = [
                'id' => $this->school->id,
                'name' => $this->school->official_name ?? $this->school->display_name ?? $this->school->short_name,
                'official_name' => $this->school->official_name,
                'display_name' => $this->school->display_name,
                'short_name' => $this->school->short_name,
            ];
        }

        // Format academic year data
        $academicYearData = null;
        if ($this->relationLoaded('academicYear') && $this->academicYear) {
            $academicYearData = [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ];
        }

        // Get current class enrollment for this student (if exists)
        // Note: class enrollment is separate from enrollment history
        // We'll try to get it from student_class_enrollments for the current academic year
        $classData = null;
        if ($this->student) {
            try {
                $currentClassEnrollment = \DB::table('student_class_enrollments')
                    ->join('classes', 'student_class_enrollments.class_id', '=', 'classes.id')
                    ->where('student_class_enrollments.student_id', $this->student_id)
                    ->where('student_class_enrollments.status', 'active')
                    ->where('classes.academic_year_id', $this->academic_year_id)
                    ->select('classes.id', 'classes.name', 'classes.code', 'classes.grade_level', 'classes.section')
                    ->first();

                if ($currentClassEnrollment) {
                    $classData = [
                        'id' => $currentClassEnrollment->id,
                        'name' => $currentClassEnrollment->name,
                        'code' => $currentClassEnrollment->code,
                        'grade_level' => $currentClassEnrollment->grade_level,
                        'section' => $currentClassEnrollment->section,
                    ];
                }
            } catch (\Exception $e) {
                // If classes table doesn't exist or query fails, classData remains null
                // This is okay - enrollment history doesn't require class enrollment
            }
        }

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'school_id' => $this->school_id,
            'academic_year_id' => $this->academic_year_id,
            'enrollment_date' => $this->enrollment_date?->format('Y-m-d'),
            'withdrawal_date' => $this->withdrawal_date?->format('Y-m-d'),
            'grade_level_at_enrollment' => $this->grade_level_at_enrollment,
            'grade_level_at_withdrawal' => $this->grade_level_at_withdrawal,
            'enrollment_type' => $this->enrollment_type,
            'withdrawal_type' => $this->withdrawal_type,
            'withdrawal_reason' => $this->withdrawal_reason,
            'previous_school' => $this->previous_school,
            'next_school' => $this->next_school,
            'enrollment_status' => $enrollmentStatus, // Derived field
            'final_gpa' => $this->final_gpa,
            'credits_earned' => $this->credits_earned,
            'created_at' => $this->created_at?->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->updated_at?->format('Y-m-d\TH:i:s\Z'),
            
            // Relationships
            'student' => $studentData,
            'school' => $schoolData,
            'academic_year' => $academicYearData,
            'class' => $classData, // Current class enrollment (if exists)
        ];
    }
}


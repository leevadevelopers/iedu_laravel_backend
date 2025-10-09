<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'school_id' => $this->school_id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'academic_term_id' => $this->academic_term_id,
            'assessment_name' => $this->assessment_name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->assessment_date?->format('Y-m-d'),
            'raw_score' => $this->raw_score ? (float) $this->raw_score : null,
            'percentage_score' => $this->percentage_score ? (float) $this->percentage_score : null,
            'letter_grade' => $this->letter_grade,
            'points_earned' => $this->points_earned ? (float) $this->points_earned : null,
            'points_possible' => $this->points_possible ? (float) $this->points_possible : null,
            'grade_category' => $this->grade_category,
            'weight' => (float) $this->weight,
            'teacher_comments' => $this->teacher_comments,
            'private_notes' => $this->private_notes,
            'entered_at' => $this->entered_at?->toISOString(),
            'modified_at' => $this->modified_at?->toISOString(),
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->user->name ?? 'N/A',
                ];
            }),
            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                ];
            }),
            'academic_term' => $this->whenLoaded('academicTerm', function () {
                return [
                    'id' => $this->academicTerm->id,
                    'name' => $this->academicTerm->name,
                ];
            }),
            'entered_by' => $this->whenLoaded('enteredBy', function () {
                return [
                    'id' => $this->enteredBy->id,
                    'name' => $this->enteredBy->user->name ?? 'N/A',
                ];
            }),
            'modified_by' => $this->whenLoaded('modifiedBy', function () {
                return $this->modifiedBy ? [
                    'id' => $this->modifiedBy->id,
                    'name' => $this->modifiedBy->user->name ?? 'N/A',
                ] : null;
            }),
            'reviews_count' => $this->whenCounted('reviews'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}


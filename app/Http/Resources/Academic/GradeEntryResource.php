<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeEntryResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'academic_term_id' => $this->academic_term_id,
            'assessment_name' => $this->assessment_name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->formatDate($this->assessment_date),
            'raw_score' => $this->formatDecimal($this->raw_score),
            'percentage_score' => $this->formatDecimal($this->percentage_score),
            'letter_grade' => $this->letter_grade,
            'points_earned' => $this->formatDecimal($this->points_earned),
            'points_possible' => $this->formatDecimal($this->points_possible),
            'grade_category' => $this->grade_category,
            'weight' => $this->formatDecimal($this->weight),
            'entered_by' => $this->entered_by,
            'entered_at' => $this->formatDateTime($this->entered_at),
            'modified_by' => $this->modified_by,
            'modified_at' => $this->formatDateTime($this->modified_at),
            'teacher_comments' => $this->teacher_comments,
            'private_notes' => $this->when(
                auth()->user() && (auth()->user()->user_type === 'teacher' || auth()->user()->user_type === 'admin'),
                $this->private_notes
            ),

            // Calculated fields
            'calculated_percentage' => $this->formatDecimal($this->calculatePercentage()),
            'weighted_score' => $this->formatDecimal($this->getWeightedScore()),
            'is_passing' => $this->isPassing(),
            'has_comments' => $this->hasComments(),
            'was_modified' => $this->wasModified(),

            // Display helpers
            'assessment_type_label' => ucfirst(str_replace('_', ' ', $this->assessment_type)),
            'grade_display' => $this->getGradeDisplay(),
            'score_display' => $this->getScoreDisplay(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'student' => $this->whenLoaded('student', new StudentResource($this->student)),
            'class' => $this->whenLoaded('class', new AcademicClassResource($this->class)),
            'academic_term' => $this->whenLoaded('academicTerm', new AcademicTermResource($this->academicTerm)),
            'entered_by_user' => $this->whenLoaded('enteredBy', new UserResource($this->enteredBy)),
            'modified_by_user' => $this->whenLoaded('modifiedBy', new UserResource($this->modifiedBy)),
        ]);
    }

    /**
     * Get formatted grade display
     */
    private function getGradeDisplay(): string
    {
        if ($this->letter_grade) {
            return $this->letter_grade . ($this->percentage_score ? ' (' . $this->percentage_score . '%)' : '');
        }

        if ($this->percentage_score) {
            return $this->percentage_score . '%';
        }

        if ($this->points_earned !== null && $this->points_possible !== null) {
            return $this->points_earned . '/' . $this->points_possible;
        }

        return $this->raw_score ? (string) $this->raw_score : 'N/A';
    }

    /**
     * Get formatted score display
     */
    private function getScoreDisplay(): string
    {
        if ($this->points_earned !== null && $this->points_possible !== null) {
            $percentage = $this->points_possible > 0
                ? round(($this->points_earned / $this->points_possible) * 100, 1)
                : 0;
            return $this->points_earned . '/' . $this->points_possible . ' (' . $percentage . '%)';
        }

        if ($this->percentage_score !== null) {
            return $this->percentage_score . '%';
        }

        return $this->raw_score ? (string) $this->raw_score : 'N/A';
    }
}

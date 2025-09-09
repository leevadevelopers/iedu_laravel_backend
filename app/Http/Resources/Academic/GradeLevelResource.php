<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeLevelResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grade_scale_id' => $this->grade_scale_id,
            'grade_value' => $this->grade_value,
            'display_value' => $this->display_value,
            'numeric_value' => $this->formatDecimal($this->numeric_value),
            'gpa_points' => $this->formatDecimal($this->gpa_points),
            'percentage_min' => $this->formatDecimal($this->percentage_min),
            'percentage_max' => $this->formatDecimal($this->percentage_max),
            'description' => $this->description,
            'color_code' => $this->color_code,
            'is_passing' => $this->is_passing,
            'sort_order' => $this->sort_order,

            // Helper fields
            'status_label' => $this->is_passing ? 'Passing' : 'Failing',
            'percentage_range' => $this->percentage_min !== null && $this->percentage_max !== null
                ? $this->percentage_min . '% - ' . $this->percentage_max . '%'
                : null,

            // Relationships
            'grade_scale' => $this->whenLoaded('gradeScale', new GradeScaleResource($this->gradeScale)),

            'meta' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
            ]
        ];
    }
}

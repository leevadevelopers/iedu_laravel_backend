<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradeScaleResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'grading_system_id' => $this->grading_system_id,
            'name' => $this->name,
            'scale_type' => $this->scale_type,
            'is_default' => $this->is_default,

            // Helper fields
            'display_name' => $this->name . ($this->is_default ? ' (Default)' : ''),
            'type_label' => ucfirst(str_replace('_', ' ', $this->scale_type)),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'grade_levels' => $this->whenLoaded('gradeLevels', GradeLevelResource::collection($this->gradeLevels)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeLevels'),
                function () {
                    $gradeLevels = $this->gradeLevels;
                    return [
                        'grade_levels_count' => $gradeLevels->count(),
                        'passing_levels_count' => $gradeLevels->where('is_passing', true)->count(),
                        'failing_levels_count' => $gradeLevels->where('is_passing', false)->count(),
                        'gpa_range' => [
                            'min' => $gradeLevels->min('gpa_points'),
                            'max' => $gradeLevels->max('gpa_points'),
                        ],
                    ];
                }
            ),
        ]);
    }
}

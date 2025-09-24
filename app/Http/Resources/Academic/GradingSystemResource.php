<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class GradingSystemResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'system_type' => $this->system_type,
            'applicable_grades' => $this->applicable_grades,
            'applicable_subjects' => $this->applicable_subjects,
            'is_primary' => $this->is_primary,
            'configuration_json' => $this->configuration_json,
            'status' => $this->status,

            // Helper fields
            'display_name' => $this->name . ($this->is_primary ? ' (Primary)' : ''),
            'type_label' => $this->getSystemTypeLabel(),

            // Configuration helpers
            'passing_threshold' => $this->configuration_json['passing_threshold'] ?? null,
            'gpa_scale' => $this->configuration_json['gpa_scale'] ?? null,
            'decimal_places' => $this->configuration_json['decimal_places'] ?? 2,

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'grade_scales' => $this->whenLoaded('gradeScales', GradeScaleResource::collection($this->gradeScales)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('gradeScales'),
                function () {
                    return [
                        'grade_scales_count' => $this->gradeScales->count(),
                        'total_grade_levels' => $this->gradeScales->sum(function ($scale) {
                            return $scale->relationLoaded('gradeLevels') ? $scale->gradeLevels->count() : 0;
                        }),
                    ];
                }
            ),
        ]);
    }

    /**
     * Get human-readable system type label
     */
    private function getSystemTypeLabel(): string
    {
        $labels = [
            'traditional_letter' => 'Letter Grades (A-F)',
            'percentage' => 'Percentage (0-100%)',
            'points' => 'Points System',
            'standards_based' => 'Standards-Based',
            'narrative' => 'Narrative Assessment'
        ];

        return $labels[$this->system_type] ?? ucfirst(str_replace('_', ' ', $this->system_type));
    }
}

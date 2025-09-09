<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class SubjectResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'subject_area' => $this->subject_area,
            'grade_levels' => $this->grade_levels,
            'learning_standards_json' => $this->learning_standards_json,
            'prerequisites' => $this->prerequisites,
            'credit_hours' => $this->formatDecimal($this->credit_hours, 1),
            'is_core_subject' => $this->is_core_subject,
            'is_elective' => $this->is_elective,
            'status' => $this->status,

            // Helper fields
            'display_name' => $this->name . ' (' . $this->code . ')',
            'subject_type' => $this->is_core_subject ? 'core' : ($this->is_elective ? 'elective' : 'regular'),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('classes') || isset($this->classes_count),
                function () {
                    return [
                        'classes_count' => $this->classes_count ?? $this->classes->count(),
                        'active_classes_count' => $this->classes_count ?? $this->classes->where('status', 'active')->count(),
                        'grade_levels_count' => count($this->grade_levels ?? []),
                    ];
                }
            ),
        ]);
    }
}

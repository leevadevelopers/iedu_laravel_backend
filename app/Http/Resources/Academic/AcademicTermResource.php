<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicTermResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'term_number' => $this->term_number,
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'instructional_days' => $this->instructional_days,
            'status' => $this->status,
            'is_current' => $this->isCurrent(),
            'duration_days' => $this->getDurationInDays(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'academic_year' => $this->whenLoaded('academicYear', new AcademicYearResource($this->academicYear)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),
            'grade_entries' => $this->whenLoaded('gradeEntries', GradeEntryResource::collection($this->gradeEntries)),

            // Statistics
            'stats' => $this->when(
                $this->resource->relationLoaded('classes'),
                function () {
                    return [
                        'classes_count' => $this->classes->count(),
                        'active_classes_count' => $this->classes->where('status', 'active')->count(),
                    ];
                }
            ),
        ]);
    }
}

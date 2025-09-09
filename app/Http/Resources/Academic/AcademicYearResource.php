<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class AcademicYearResource extends BaseAcademicResource
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
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'term_structure' => $this->term_structure,
            'total_terms' => $this->total_terms,
            'total_instructional_days' => $this->total_instructional_days,
            'status' => $this->status,
            'is_current' => $this->is_current,
            'duration_days' => $this->getDurationInDays(),

            // Relationships
            'school' => $this->whenLoaded('school', new SchoolResource($this->school)),
            'terms' => $this->whenLoaded('terms', AcademicTermResource::collection($this->terms)),
            'classes' => $this->whenLoaded('classes', AcademicClassResource::collection($this->classes)),
            'students' => $this->whenLoaded('students', StudentResource::collection($this->students)),

            // Statistics (when needed)
            'stats' => $this->when(
                $this->resource->relationLoaded('terms'),
                function () {
                    return [
                        'terms_count' => $this->terms->count(),
                        'active_terms_count' => $this->terms->where('status', 'active')->count(),
                    ];
                }
            ),
        ]);
    }
}

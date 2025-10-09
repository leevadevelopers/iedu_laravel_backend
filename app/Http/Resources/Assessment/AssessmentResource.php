<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'term_id' => $this->term_id,
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'type_id' => $this->type_id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'scheduled_date' => $this->scheduled_date?->toISOString(),
            'submission_deadline' => $this->submission_deadline?->toISOString(),
            'total_marks' => (float) $this->total_marks,
            'weight' => (float) $this->weight,
            'visibility' => $this->visibility,
            'allow_upload_submissions' => $this->allow_upload_submissions,
            'status' => $this->status,
            'is_locked' => $this->is_locked,
            'published_at' => $this->published_at?->toISOString(),
            'metadata' => $this->metadata,
            'term' => $this->whenLoaded('term', function () {
                return [
                    'id' => $this->term->id,
                    'name' => $this->term->name,
                ];
            }),
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                ];
            }),
            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                ];
            }),
            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'name' => $this->teacher->name,
                ];
            }),
            'type' => $this->whenLoaded('type', function () {
                return new AssessmentTypeResource($this->type);
            }),
            'components' => $this->whenLoaded('components', function () {
                return AssessmentComponentResource::collection($this->components);
            }),
            'resources' => $this->whenLoaded('resources', function () {
                return AssessmentResourceResource::collection($this->resources);
            }),
            'grade_entries_count' => $this->whenCounted('gradeEntries'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}


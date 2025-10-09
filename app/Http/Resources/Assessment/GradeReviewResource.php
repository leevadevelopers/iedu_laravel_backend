<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradeReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'grade_entry_id' => $this->grade_entry_id,
            'requester_id' => $this->requester_id,
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status,
            'reviewer_comments' => $this->reviewer_comments,
            'original_marks' => $this->original_marks ? (float) $this->original_marks : null,
            'revised_marks' => $this->revised_marks ? (float) $this->revised_marks : null,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'grade_entry' => $this->whenLoaded('gradeEntry', function () {
                return new GradeEntryResource($this->gradeEntry);
            }),
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester->id,
                    'name' => $this->requester->name,
                ];
            }),
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return $this->reviewer ? [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}


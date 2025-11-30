<?php

namespace App\Http\Resources\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenceJustificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                ];
            }),
            'date' => $this->date->toISOString(),
            'reason' => $this->reason,
            'description' => $this->description,
            'attachment_ids' => $this->attachment_ids,
            'status' => $this->status,
            'submitted_by' => $this->whenLoaded('submitter', function () {
                return [
                    'id' => $this->submitter->id,
                    'name' => $this->submitter->name,
                ];
            }),
            'reviewed_by' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                ];
            }),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


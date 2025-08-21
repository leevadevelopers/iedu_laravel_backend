<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'dates' => [
                'target_date' => $this->target_date?->format('Y-m-d'),
                'completion_date' => $this->completion_date?->format('Y-m-d'),
                'days_until_due' => $this->days_until_due,
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
                'color' => $this->status?->color(),
                'progress_status' => $this->progress_status,
            ],
            'weight' => $this->weight,
            'deliverables' => $this->deliverables,
            'success_criteria' => $this->success_criteria,
            'dependencies' => $this->dependencies,
            'notes' => $this->notes,
            'is_overdue' => $this->is_overdue,
            'responsible_user' => $this->whenLoaded('responsibleUser', function () {
                return [
                    'id' => $this->responsibleUser->id,
                    'name' => $this->responsibleUser->name,
                    'email' => $this->responsibleUser->email,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

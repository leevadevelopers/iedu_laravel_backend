<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => [
                'value' => $this->priority?->value,
                'label' => $this->priority?->label(),
                'color' => $this->priority?->color(),
                'weight' => $this->priority?->weight(),
            ],
            'status' => [
                'value' => $this->status?->value,
                'label' => $this->status?->label(),
                'color' => $this->status?->color(),
            ],
            'dates' => [
                'start_date' => $this->start_date?->format('Y-m-d'),
                'end_date' => $this->end_date?->format('Y-m-d'),
                'duration_days' => $this->duration_in_days,
            ],
            'financial' => [
                'budget' => $this->budget,
                'currency' => $this->currency,
                'utilization_percentage' => $this->budget_utilization,
            ],
            'methodology' => [
                'type' => $this->methodology_type,
                'label' => \App\Enums\Project\MethodologyType::from($this->methodology_type)->label(),
                'requirements' => $this->methodology_requirements,
            ],
            'progress' => [
                'percentage' => $this->progress_percentage,
                'health_score' => $this->health_score,
                'risk_score' => $this->risk_score,
            ],
            'compliance_requirements' => $this->compliance_requirements,
            'metadata' => $this->metadata,
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'milestones' => $this->whenLoaded('milestones', function () {
                return ProjectMilestoneResource::collection($this->milestones);
            }),
            'team' => $this->whenLoaded('team', function () {
                return $this->team->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->pivot->role,
                        'access_level' => $member->pivot->access_level,
                        'joined_at' => $member->pivot->joined_at,
                    ];
                });
            }),
            'stakeholders' => $this->whenLoaded('stakeholders'),
            'risks' => $this->whenLoaded('risks'),
            
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
        ];
    }
}

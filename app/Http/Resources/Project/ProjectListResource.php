<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'category' => $this->category,
            'priority' => [
                'value' => $this->priority?->value,
                'label' => $this->priority?->label(),
                'color' => $this->priority?->color(),
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
            'budget' => $this->budget,
            'currency' => $this->currency,
            'methodology_type' => $this->methodology_type,
            'progress_percentage' => $this->progress_percentage,
            'health_score' => $this->health_score['overall'] ?? 0,
            'creator_name' => $this->creator?->name,
            'milestones_count' => $this->whenCounted('milestones'),
            'team_count' => $this->whenCounted('team'),
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}

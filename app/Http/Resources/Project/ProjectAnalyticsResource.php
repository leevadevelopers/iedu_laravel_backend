<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'project_id' => $this->resource['project']->id,
            'project_name' => $this->resource['project']->name,
            'analytics' => [
                'progress' => $this->resource['progress'],
                'health' => $this->resource['health'],
                'budget_status' => $this->resource['budget_status'],
                'risks' => $this->resource['risks'],
                'timeline' => $this->resource['timeline'],
            ],
            'insights' => $this->resource['insights'] ?? [],
            'generated_at' => now()->toISOString(),
        ];
    }
}

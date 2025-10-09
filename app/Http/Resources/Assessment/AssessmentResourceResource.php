<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_id' => $this->assessment_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'url_or_path' => $this->url_or_path,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'access_policy' => $this->access_policy,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}


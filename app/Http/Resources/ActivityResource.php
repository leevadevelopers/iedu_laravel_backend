<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'human_description' => $this->human_description,
            'event' => $this->event,
            'batch_uuid' => $this->batch_uuid,
            'created_at' => $this->created_at?->toISOString(),
            
            'subject' => $this->when($this->subject, [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
                'name' => $this->subject?->name ?? $this->subject?->title ?? null,
            ]),
            
            'causer' => $this->when($this->causer, [
                'type' => $this->causer_type,
                'id' => $this->causer_id,
                'name' => $this->causer?->name ?? null,
                'email' => $this->causer?->email ?? null,
            ]),
            
            'properties' => $this->properties,
            'metadata' => $this->metadata,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
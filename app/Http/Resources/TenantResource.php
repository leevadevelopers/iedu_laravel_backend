<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'features' => $this->getFeatures(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            'user_role' => $this->when(isset($this->user_role), $this->user_role),
            'is_current' => $this->when(isset($this->is_current), $this->is_current),
            'user_status' => $this->when(isset($this->user_status), $this->user_status),
            'joined_at' => $this->when(isset($this->joined_at), $this->joined_at),
            
            'owner' => $this->when($this->relationLoaded('owner'), function () {
                return new UserResource($this->owner());
            }),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }
}
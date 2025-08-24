<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'type' => $this->type,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'profile_photo_path' => $this->profile_photo_path,
            'is_active' => $this->is_active,
            'verified_at' => $this->verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            'tenant_role' => $this->when(isset($this->tenant_role), function () {
                return [
                    'id' => $this->tenant_role->id,
                    'name' => $this->tenant_role->name,
                    'display_name' => $this->tenant_role->display_name,
                ];
            }),
            'custom_permissions' => $this->when(isset($this->custom_permissions), $this->custom_permissions),
            'tenant_status' => $this->when(isset($this->tenant_status), $this->tenant_status),
            'joined_at' => $this->when(isset($this->joined_at), $this->joined_at),
            
            'current_tenant_context' => $this->when($request->user()?->id === $this->id, function () {
                return $this->getTenantContext();
            }),
        ];
    }
}
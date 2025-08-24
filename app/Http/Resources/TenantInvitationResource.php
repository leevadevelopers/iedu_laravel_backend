<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'identifier' => $this->identifier,
            'type' => $this->type,
            'role' => $this->role,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'message' => $this->message,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'inviter' => $this->whenLoaded('inviter', function () {
                return [
                    'id' => $this->inviter->id,
                    'name' => $this->inviter->name,
                    'email' => $this->inviter->email,
                ];
            }),
            'tenant' => $this->whenLoaded('tenant', function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'slug' => $this->tenant->slug,
                ];
            }),
            'is_expired' => $this->isExpired(),
            'is_pending' => $this->isPending(),
            'can_be_accepted' => $this->canBeAccepted(),
        ];
    }
}

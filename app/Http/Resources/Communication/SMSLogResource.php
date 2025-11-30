<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SMSLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient_phone' => $this->recipient_phone,
            'message' => $this->message,
            'template_id' => $this->template_id,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_message_id' => $this->provider_message_id,
            'cost' => $this->cost ? (float) $this->cost : null,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


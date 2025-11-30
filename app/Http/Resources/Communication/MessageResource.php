<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'message' => $this->message,
            'thread_id' => $this->thread_id,
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                ];
            }),
            'recipient_ids' => $this->recipient_ids,
            'student_ids' => $this->student_ids,
            'channels' => $this->channels,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toISOString(),
            'replies' => $this->whenLoaded('replies', function () {
                return MessageResource::collection($this->replies);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


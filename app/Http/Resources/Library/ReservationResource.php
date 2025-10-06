<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => [
                'id' => $this->book->id,
                'title' => $this->book->title,
                'isbn' => $this->book->isbn,
                'cover_image' => $this->book->cover_image,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'reserved_at' => $this->reserved_at->toISOString(),
            'expires_at' => $this->expires_at->toISOString(),
            'notified_at' => $this->notified_at?->toISOString(),
            'status' => $this->status,
            'is_expired' => $this->isExpired(),
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

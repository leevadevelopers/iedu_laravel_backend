<?php

namespace App\Http\Resources\Reception;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                ];
            }),
            'purpose' => $this->purpose,
            'resolved' => $this->resolved,
            'notes' => $this->notes,
            'arrived_at' => $this->arrived_at?->toISOString(),
            'departed_at' => $this->departed_at?->toISOString(),
            'attended_by' => $this->whenLoaded('attendedBy', function () {
                return [
                    'id' => $this->attendedBy->id,
                    'name' => $this->attendedBy->name,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


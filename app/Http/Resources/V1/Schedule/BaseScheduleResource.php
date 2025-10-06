<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseScheduleResource extends JsonResource
{
    protected function formatDateTime($datetime): ?string
    {
        return $datetime ? $datetime->format('Y-m-d H:i:s') : null;
    }

    protected function formatDate($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    protected function formatTime($time): ?string
    {
        return $time ? $time->format('H:i') : null;
    }

    // Use JsonResource::whenLoaded from the framework for proper MissingValue handling

    protected function addMetadata(array $data): array
    {
        return array_merge($data, [
            'meta' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
                'school_id' => $this->school_id,
            ]
        ]);
    }
}

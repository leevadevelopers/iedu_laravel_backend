<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseAcademicResource extends JsonResource
{
    /**
     * Format datetime for API response
     */
    protected function formatDateTime($datetime): ?string
    {
        return $datetime ? $datetime->format('Y-m-d H:i:s') : null;
    }

    /**
     * Format date for API response
     */
    protected function formatDate($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Format time for API response
     */
    protected function formatTime($time): ?string
    {
        return $time ? $time->format('H:i:s') : null;
    }

    /**
     * Format decimal for API response
     */
    protected function formatDecimal($value, int $decimals = 2): ?float
    {
        return $value !== null ? round((float) $value, $decimals) : null;
    }

    /**
     * Check if a relation should be loaded
     */
    protected function whenLoaded(string $relation, $value = null)
    {
        return $this->resource->relationLoaded($relation) ?
            ($value ?? $this->resource->{$relation}) :
            $this->missingValue();
    }

    /**
     * Add common metadata to resource
     */
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

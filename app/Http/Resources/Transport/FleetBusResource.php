<?php

namespace App\Http\Resources\Transport;

use Illuminate\Http\Resources\Json\JsonResource;

class FleetBusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', function () {
                return [
                    'id' => $this->school->id,
                    'name' => $this->school->name,
                ];
            }),
            'license_plate' => $this->license_plate,
            'internal_code' => $this->internal_code,
            'make' => $this->make,
            'model' => $this->model,
            'manufacture_year' => $this->manufacture_year,
            'capacity' => $this->capacity,
            'current_capacity' => $this->current_capacity,
            'status' => $this->status,
            'gps_device_id' => $this->gps_device_id,
            'latest_tracking' => $this->whenLoaded('latestTracking', function () {
                return [
                    'latitude' => $this->latestTracking->latitude,
                    'longitude' => $this->latestTracking->longitude,
                    'speed_kmh' => $this->latestTracking->speed_kmh,
                    'tracked_at' => optional($this->latestTracking->tracked_at)->toIso8601String(),
                ];
            }),
            'is_available' => $this->isAvailable(),
        ];
    }
}


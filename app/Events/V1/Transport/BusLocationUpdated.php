<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\TransportTracking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tracking;

    public function __construct(TransportTracking $tracking)
    {
        $this->tracking = $tracking->load(['fleetBus', 'transportRoute', 'currentStop', 'nextStop']);
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('school.' . $this->tracking->school_id . '.transport'),
            new PrivateChannel('bus.' . $this->tracking->fleet_bus_id),
            new PrivateChannel('route.' . $this->tracking->transport_route_id)
        ];
    }

    public function broadcastWith()
    {
        return [
            'bus' => [
                'id' => $this->tracking->fleetBus->id,
                'license_plate' => $this->tracking->fleetBus->license_plate,
                'internal_code' => $this->tracking->fleetBus->internal_code
            ],
            'route' => [
                'id' => $this->tracking->transportRoute->id,
                'name' => $this->tracking->transportRoute->name
            ],
            'location' => [
                'latitude' => (float) $this->tracking->latitude,
                'longitude' => (float) $this->tracking->longitude,
                'speed_kmh' => (float) $this->tracking->speed_kmh,
                'heading' => $this->tracking->heading,
                'altitude' => $this->tracking->altitude
            ],
            'status' => $this->tracking->status,
            'current_stop' => $this->tracking->currentStop ? [
                'id' => $this->tracking->currentStop->id,
                'name' => $this->tracking->currentStop->name,
                'address' => $this->tracking->currentStop->address
            ] : null,
            'next_stop' => $this->tracking->nextStop ? [
                'id' => $this->tracking->nextStop->id,
                'name' => $this->tracking->nextStop->name,
                'eta_minutes' => $this->tracking->eta_minutes
            ] : null,
            'timestamp' => $this->tracking->tracked_at->toISOString(),
            'is_moving' => $this->tracking->speed_kmh > 1
        ];
    }

    public function broadcastAs()
    {
        return 'bus.location.updated';
    }
}

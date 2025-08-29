<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\TransportIncident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransportIncidentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $incident;

    public function __construct(TransportIncident $incident)
    {
        $this->incident = $incident->load(['fleetBus', 'transportRoute', 'reportedBy']);
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('school.' . $this->incident->school_id . '.transport'),
            new PrivateChannel('incidents'),
            new PrivateChannel('bus.' . $this->incident->fleet_bus_id)
        ];
    }

    public function broadcastWith()
    {
        return [
            'incident' => [
                'id' => $this->incident->id,
                'type' => $this->incident->incident_type,
                'severity' => $this->incident->severity,
                'title' => $this->incident->title,
                'description' => $this->incident->description,
                'status' => $this->incident->status,
                'datetime' => $this->incident->incident_datetime->toISOString()
            ],
            'bus' => [
                'id' => $this->incident->fleetBus->id,
                'license_plate' => $this->incident->fleetBus->license_plate,
                'internal_code' => $this->incident->fleetBus->internal_code
            ],
            'route' => $this->incident->transportRoute ? [
                'id' => $this->incident->transportRoute->id,
                'name' => $this->incident->transportRoute->name
            ] : null,
            'location' => $this->incident->incident_latitude ? [
                'latitude' => (float) $this->incident->incident_latitude,
                'longitude' => (float) $this->incident->incident_longitude
            ] : null,
            'reported_by' => [
                'id' => $this->incident->reportedBy->id,
                'name' => $this->incident->reportedBy->first_name . ' ' . $this->incident->reportedBy->last_name
            ],
            'affected_students_count' => count($this->incident->affected_students ?? []),
            'requires_immediate_attention' => in_array($this->incident->severity, ['high', 'critical'])
        ];
    }

    public function broadcastAs()
    {
        return 'transport.incident.created';
    }
}

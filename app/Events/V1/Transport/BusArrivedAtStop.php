<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\TransportTracking;
use App\Models\V1\Transport\BusStop;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusArrivedAtStop implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tracking;
    public $busStop;

    public function __construct(TransportTracking $tracking, BusStop $busStop)
    {
        $this->tracking = $tracking->load(['fleetBus', 'transportRoute']);
        $this->busStop = $busStop;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('school.' . $this->tracking->school_id . '.transport'),
            new PrivateChannel('bus.' . $this->tracking->fleet_bus_id),
            new PrivateChannel('stop.' . $this->busStop->id),
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
            'stop' => [
                'id' => $this->busStop->id,
                'name' => $this->busStop->name,
                'address' => $this->busStop->address,
                'order' => $this->busStop->stop_order
            ],
            'arrival_time' => $this->tracking->tracked_at->toISOString(),
            'scheduled_time' => $this->busStop->scheduled_arrival_time,
            'is_on_time' => $this->isOnTime(),
            'delay_minutes' => $this->getDelayMinutes(),
            'expected_students' => $this->getExpectedStudents()
        ];
    }

    public function broadcastAs()
    {
        return 'bus.arrived.at.stop';
    }

    private function isOnTime(): bool
    {
        $scheduledTime = now()->setTimeFromTimeString($this->busStop->scheduled_arrival_time);
        $actualTime = $this->tracking->tracked_at;

        // Consider on-time if within 5 minutes of scheduled time
        return abs($scheduledTime->diffInMinutes($actualTime)) <= 5;
    }

    public function getDelayMinutes(): int
    {
        $scheduledTime = now()->setTimeFromTimeString($this->busStop->scheduled_arrival_time);
        $actualTime = $this->tracking->tracked_at;

        return $actualTime->greaterThan($scheduledTime)
            ? $scheduledTime->diffInMinutes($actualTime)
            : 0;
    }

    private function getExpectedStudents(): int
    {
        return $this->busStop->pickupSubscriptions()
            ->where('status', 'active')
            ->count();
    }
}

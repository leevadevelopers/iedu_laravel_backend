<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\StudentTransportEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentCheckedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $event;

    public function __construct(StudentTransportEvent $event)
    {
        $this->event = $event->load(['student', 'fleetBus', 'busStop', 'transportRoute']);
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('school.' . $this->event->school_id . '.transport'),
            new PrivateChannel('parent.' . $this->getParentIds()),
            new PrivateChannel('bus.' . $this->event->fleet_bus_id)
        ];
    }

    public function broadcastWith()
    {
        return [
            'event_id' => $this->event->id,
            'student' => [
                'id' => $this->event->student->id,
                'name' => $this->event->student->first_name . ' ' . $this->event->student->last_name,
                'student_number' => $this->event->student->student_number
            ],
            'bus' => [
                'id' => $this->event->fleetBus->id,
                'license_plate' => $this->event->fleetBus->license_plate,
                'internal_code' => $this->event->fleetBus->internal_code
            ],
            'stop' => [
                'id' => $this->event->busStop->id,
                'name' => $this->event->busStop->name,
                'address' => $this->event->busStop->address
            ],
            'route' => [
                'id' => $this->event->transportRoute->id,
                'name' => $this->event->transportRoute->name
            ],
            'timestamp' => $this->event->event_timestamp->toISOString(),
            'validation_method' => $this->event->validation_method
        ];
    }

    public function broadcastAs()
    {
        return 'student.checked.in';
    }

    private function getParentIds()
    {
        // Get parent user IDs for this student
        $subscription = $this->event->student->transportSubscriptions()
            ->where('status', 'active')
            ->first();

        if ($subscription && $subscription->authorized_parents) {
            return implode(',', $subscription->authorized_parents);
        }

        return '';
    }
}

#!/bin/bash

# Transport Module - Events & Listeners Generator
echo "📡 Creating Transport Module Events and Listeners..."

# 1. StudentCheckedIn Event
cat > app/Events/V1/Transport/StudentCheckedIn.php << 'EOF'
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
EOF

# 2. StudentCheckedOut Event
cat > app/Events/V1/Transport/StudentCheckedOut.php << 'EOF'
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

class StudentCheckedOut implements ShouldBroadcast
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
            'validation_method' => $this->event->validation_method,
            'arrival_status' => 'arrived_at_school'
        ];
    }

    public function broadcastAs()
    {
        return 'student.checked.out';
    }

    private function getParentIds()
    {
        $subscription = $this->event->student->transportSubscriptions()
            ->where('status', 'active')
            ->first();

        if ($subscription && $subscription->authorized_parents) {
            return implode(',', $subscription->authorized_parents);
        }

        return '';
    }
}
EOF

# 3. BusLocationUpdated Event
cat > app/Events/V1/Transport/BusLocationUpdated.php << 'EOF'
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
EOF

# 4. BusArrivedAtStop Event
cat > app/Events/V1/Transport/BusArrivedAtStop.php << 'EOF'
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

    private function getDelayMinutes(): int
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
EOF

# 5. TransportIncidentCreated Event
cat > app/Events/V1/Transport/TransportIncidentCreated.php << 'EOF'
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
EOF

# 6. BusMaintenanceScheduled Event
cat > app/Events/V1/Transport/BusMaintenanceScheduled.php << 'EOF'
<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusMaintenanceScheduled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bus;
    public $maintenanceType;
    public $scheduledDate;

    public function __construct(FleetBus $bus, string $maintenanceType, $scheduledDate)
    {
        $this->bus = $bus;
        $this->maintenanceType = $maintenanceType;
        $this->scheduledDate = $scheduledDate;
    }
}
EOF

# 7. RouteOptimized Event
cat > app/Events/V1/Transport/RouteOptimized.php << 'EOF'
<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\TransportRoute;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteOptimized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $route;
    public $optimizationResults;

    public function __construct(TransportRoute $route, array $optimizationResults)
    {
        $this->route = $route;
        $this->optimizationResults = $optimizationResults;
    }
}
EOF

# 8. Now create the Listeners
echo "📡 Creating Transport Event Listeners..."

# 9. SendStudentCheckinNotification Listener
cat > app/Listeners/V1/Transport/SendStudentCheckinNotification.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\Transport\StudentCheckedIn;
use App\Jobs\Transport\SendTransportNotification;
use App\Models\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentCheckinNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentCheckedIn $event)
    {
        $subscription = StudentTransportSubscription::where('student_id', $event->event->student_id)
            ->where('status', 'active')
            ->first();

        if (!$subscription || !$subscription->authorized_parents) {
            return;
        }

        foreach ($subscription->authorized_parents as $parentId) {
            // Send notification via multiple channels
            SendTransportNotification::dispatch([
                'parent_id' => $parentId,
                'student_id' => $event->event->student_id,
                'type' => 'check_in',
                'channels' => ['email', 'sms', 'push'],
                'data' => [
                    'student_name' => $event->event->student->first_name . ' ' . $event->event->student->last_name,
                    'bus_info' => $event->event->fleetBus->license_plate,
                    'stop_name' => $event->event->busStop->name,
                    'time' => $event->event->event_timestamp->format('H:i'),
                    'route_name' => $event->event->transportRoute->name
                ]
            ]);
        }
    }
}
EOF

# 10. SendStudentCheckoutNotification Listener
cat > app/Listeners/V1/Transport/SendStudentCheckoutNotification.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedOut;
use App\Jobs\V1\Transport\SendTransportNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentCheckoutNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentCheckedOut $event)
    {
        $subscription = StudentTransportSubscription::where('student_id', $event->event->student_id)
            ->where('status', 'active')
            ->first();

        if (!$subscription || !$subscription->authorized_parents) {
            return;
        }

        foreach ($subscription->authorized_parents as $parentId) {
            SendTransportNotification::dispatch([
                'parent_id' => $parentId,
                'student_id' => $event->event->student_id,
                'type' => 'check_out',
                'channels' => ['email', 'sms', 'push'],
                'data' => [
                    'student_name' => $event->event->student->first_name . ' ' . $event->event->student->last_name,
                    'bus_info' => $event->event->fleetBus->license_plate,
                    'stop_name' => $event->event->busStop->name,
                    'arrival_time' => $event->event->event_timestamp->format('H:i'),
                    'status' => 'Arrived at school safely'
                ]
            ]);
        }
    }
}
EOF

# 11. UpdateBusCapacity Listener
cat > app/Listeners/V1/Transport/UpdateBusCapacity.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateBusCapacity implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle($event)
    {
        $bus = $event->event->fleetBus;

        if ($event instanceof StudentCheckedIn) {
            // Increment current capacity
            $bus->increment('current_capacity');
        } elseif ($event instanceof StudentCheckedOut) {
            // Decrement current capacity
            $bus->decrement('current_capacity');
        }

        // Ensure capacity doesn't go below 0 or above maximum
        $bus->current_capacity = max(0, min($bus->current_capacity, $bus->capacity));
        $bus->save();
    }
}
EOF

# 12. ProcessBusDelayAlert Listener
cat > app/Listeners/V1/Transport/ProcessBusDelayAlert.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\BusArrivedAtStop;
use App\Jobs\V1\Transport\SendDelayNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessBusDelayAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(BusArrivedAtStop $event)
    {
        // Only process if there's a significant delay (>10 minutes)
        if ($event->getDelayMinutes() <= 10) {
            return;
        }

        // Get all students who use this stop as pickup point
        $subscriptions = StudentTransportSubscription::where('pickup_stop_id', $event->busStop->id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->authorized_parents) {
                foreach ($subscription->authorized_parents as $parentId) {
                    SendDelayNotification::dispatch([
                        'parent_id' => $parentId,
                        'student_id' => $subscription->student_id,
                        'delay_minutes' => $event->getDelayMinutes(),
                        'stop_name' => $event->busStop->name,
                        'bus_info' => $event->tracking->fleetBus->license_plate,
                        'new_eta' => now()->addMinutes(5)->format('H:i') // Estimated boarding time
                    ]);
                }
            }
        }
    }
}
EOF

# 13. HandleTransportIncident Listener
cat > app/Listeners/V1/Transport/HandleTransportIncident.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\TransportIncidentCreated;
use App\Jobs\V1\Transport\NotifyIncidentStakeholders;
use App\Jobs\V1\Transport\CreateIncidentWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleTransportIncident implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TransportIncidentCreated $event)
    {
        $incident = $event->incident;

        // Notify relevant stakeholders immediately
        NotifyIncidentStakeholders::dispatch($incident);

        // Create workflow for incident resolution if using Forms Engine
        CreateIncidentWorkflow::dispatch($incident);

        // If critical incident, trigger emergency protocols
        if ($incident->severity === 'critical') {
            $this->handleCriticalIncident($incident);
        }

        // If students are affected, notify their parents
        if (!empty($incident->affected_students)) {
            $this->notifyAffectedParents($incident);
        }
    }

    private function handleCriticalIncident($incident)
    {
        // Implement emergency notification logic
        // Could involve SMS to school admin, emergency services, etc.
    }

    private function notifyAffectedParents($incident)
    {
        foreach ($incident->affected_students as $studentId) {
            $subscription = \App\Models\Transport\StudentTransportSubscription::where('student_id', $studentId)
                ->where('status', 'active')
                ->first();

            if ($subscription && $subscription->authorized_parents) {
                foreach ($subscription->authorized_parents as $parentId) {
                    \App\Jobs\Transport\SendIncidentNotification::dispatch([
                        'parent_id' => $parentId,
                        'student_id' => $studentId,
                        'incident_id' => $incident->id,
                        'incident_type' => $incident->incident_type,
                        'severity' => $incident->severity,
                        'description' => $incident->description,
                        'immediate_action' => $incident->immediate_action_taken
                    ]);
                }
            }
        }
    }
}
EOF

# 14. LogTransportActivity Listener
cat > app/Listeners/V1/Transport/LogTransportActivity.php << 'EOF'
<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use App\Events\V1\Transport\BusLocationUpdated;
use App\Events\V1\Transport\TransportIncidentCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogTransportActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle($event)
    {
        $logData = $this->prepareLogData($event);

        // Log to activity log for audit trail
        activity()
            ->performedOn($this->getSubject($event))
            ->withProperties($logData)
            ->log($this->getLogMessage($event));
    }

    private function prepareLogData($event): array
    {
        if ($event instanceof StudentCheckedIn || $event instanceof StudentCheckedOut) {
            return [
                'student_id' => $event->event->student_id,
                'bus_id' => $event->event->fleet_bus_id,
                'stop_id' => $event->event->bus_stop_id,
                'route_id' => $event->event->transport_route_id,
                'event_type' => $event->event->event_type,
                'timestamp' => $event->event->event_timestamp,
                'validation_method' => $event->event->validation_method
            ];
        }

        if ($event instanceof BusLocationUpdated) {
            return [
                'bus_id' => $event->tracking->fleet_bus_id,
                'route_id' => $event->tracking->transport_route_id,
                'latitude' => $event->tracking->latitude,
                'longitude' => $event->tracking->longitude,
                'speed' => $event->tracking->speed_kmh,
                'status' => $event->tracking->status
            ];
        }

        if ($event instanceof TransportIncidentCreated) {
            return [
                'incident_id' => $event->incident->id,
                'bus_id' => $event->incident->fleet_bus_id,
                'incident_type' => $event->incident->incident_type,
                'severity' => $event->incident->severity,
                'reported_by' => $event->incident->reported_by
            ];
        }

        return [];
    }

    private function getSubject($event)
    {
        if ($event instanceof StudentCheckedIn || $event instanceof StudentCheckedOut) {
            return $event->event->student;
        }

        if ($event instanceof BusLocationUpdated) {
            return $event->tracking->fleetBus;
        }

        if ($event instanceof TransportIncidentCreated) {
            return $event->incident;
        }

        return null;
    }

    private function getLogMessage($event): string
    {
        if ($event instanceof StudentCheckedIn) {
            return 'Student checked into transport';
        }

        if ($event instanceof StudentCheckedOut) {
            return 'Student checked out of transport';
        }

        if ($event instanceof BusLocationUpdated) {
            return 'Bus location updated';
        }

        if ($event instanceof TransportIncidentCreated) {
            return 'Transport incident created';
        }

        return 'Transport activity logged';
    }
}
EOF

# 15. Create Event Service Provider Registration
cat > app/Providers/TransportEventServiceProvider.php << 'EOF'
<?php

namespace App\Providers;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use App\Events\V1\Transport\BusLocationUpdated;
use App\Events\V1\Transport\BusArrivedAtStop;
use App\Events\V1\Transport\TransportIncidentCreated;
use App\Events\V1\Transport\BusMaintenanceScheduled;
use App\Events\V1\Transport\RouteOptimized;

use App\Listeners\V1\Transport\SendStudentCheckinNotification;
use App\Listeners\V1\Transport\SendStudentCheckoutNotification;
use App\Listeners\V1\Transport\UpdateBusCapacity;
use App\Listeners\V1\Transport\ProcessBusDelayAlert;
use App\Listeners\V1\Transport\HandleTransportIncident;
use App\Listeners\V1\Transport\LogTransportActivity;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class TransportEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        StudentCheckedIn::class => [
            SendStudentCheckinNotification::class,
            UpdateBusCapacity::class,
            LogTransportActivity::class,
        ],

        StudentCheckedOut::class => [
            SendStudentCheckoutNotification::class,
            UpdateBusCapacity::class,
            LogTransportActivity::class,
        ],

        BusLocationUpdated::class => [
            LogTransportActivity::class,
        ],

        BusArrivedAtStop::class => [
            ProcessBusDelayAlert::class,
        ],

        TransportIncidentCreated::class => [
            HandleTransportIncident::class,
            LogTransportActivity::class,
        ],

        BusMaintenanceScheduled::class => [
            // Add maintenance-related listeners here
        ],

        RouteOptimized::class => [
            // Add route optimization listeners here
        ],
    ];

    public function boot()
    {
        //
    }
}
EOF

echo "✅ Transport module events and listeners created successfully!"
echo "📝 Events include:"
echo "   - Student check-in/check-out events with real-time broadcasting"
echo "   - Bus location tracking and geofencing"
echo "   - Incident management and emergency alerts"
echo "   - Parent notifications via multiple channels"
echo "   - Activity logging for audit trails"
echo ""
echo "🔔 Don't forget to:"
echo "   1. Register TransportEventServiceProvider in config/app.php"
echo "   2. Configure broadcasting driver (Pusher, Redis, etc.)"
echo "   3. Set up notification channels (email, SMS, push)"

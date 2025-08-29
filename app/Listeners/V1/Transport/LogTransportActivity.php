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

<?php

namespace App\Services\V1\Transport;

use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Models\V1\Transport\TransportNotification;
use Illuminate\Database\Eloquent\Collection;

class ParentPortalService
{
    public function getDashboard(User $parent): array
    {
        $students = $this->getParentStudents($parent);

        $dashboard = [
            'students' => [],
            'notifications' => $this->getRecentNotifications($parent, 5),
            'summary' => [
                'total_students' => $students->count(),
                'active_subscriptions' => 0,
                'unread_notifications' => 0,
                'recent_incidents' => 0
            ]
        ];

        foreach ($students as $student) {
            $subscription = $student->transportSubscriptions()
                ->where('status', 'active')
                ->with(['transportRoute', 'pickupStop', 'dropoffStop'])
                ->first();

            if ($subscription) {
                $studentData = [
                    'student' => $student,
                    'subscription' => $subscription,
                    'current_status' => $this->getCurrentTransportStatus($student),
                    'today_events' => $this->getTodayEvents($student),
                    'bus_location' => $this->getStudentBusLocation($student)
                ];

                $dashboard['students'][] = $studentData;
                $dashboard['summary']['active_subscriptions']++;
            }
        }

        $dashboard['summary']['unread_notifications'] = $this->getUnreadNotificationsCount($parent);

        return $dashboard;
    }

    public function getStudentTransportStatus(Student $student): array
    {
        $subscription = $student->transportSubscriptions()
            ->where('status', 'active')
            ->with(['transportRoute', 'pickupStop', 'dropoffStop'])
            ->first();

        if (!$subscription) {
            return ['status' => 'not_subscribed'];
        }

        $todayEvents = $this->getTodayEvents($student);
        $busLocation = $this->getStudentBusLocation($student);

        return [
            'status' => $this->determineCurrentStatus($todayEvents, $busLocation),
            'subscription' => $subscription,
            'today_events' => $todayEvents,
            'bus_location' => $busLocation,
            'next_pickup_time' => $subscription->pickupStop->scheduled_arrival_time ?? null,
            'estimated_arrival' => $this->getEstimatedArrival($subscription)
        ];
    }

    public function getStudentBusLocation(Student $student): ?array
    {
        $subscription = $student->transportSubscriptions()
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return null;
        }

        $bus = $subscription->transportRoute->getCurrentBus();
        if (!$bus) {
            return null;
        }

        $location = $bus->latestTracking;
        if (!$location || $location->tracked_at < now()->subMinutes(10)) {
            return null;
        }

        return [
            'bus' => $bus,
            'location' => $location,
            'is_moving' => $location->speed_kmh > 1,
            'last_updated' => $location->tracked_at->diffForHumans(),
            'eta_to_pickup' => $this->calculateEtaToStop($location, $subscription->pickupStop)
        ];
    }

    public function getTransportHistory(Student $student, array $filters = []): array
    {
        $query = StudentTransportEvent::where('student_id', $student->id)
            ->with(['fleetBus', 'busStop', 'transportRoute']);

        if (isset($filters['from_date'])) {
            $query->whereDate('event_timestamp', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('event_timestamp', '<=', $filters['to_date']);
        }

        $limit = $filters['limit'] ?? 50;
        $events = $query->orderBy('event_timestamp', 'desc')
            ->limit($limit)
            ->get();

        return [
            'events' => $events,
            'summary' => [
                'total_trips' => $events->where('event_type', 'check_in')->count(),
                'on_time_rate' => $this->calculateOnTimeRate($events),
                'favorite_stop' => $this->getFavoriteStop($events),
                'average_trip_duration' => $this->getAverageTripDuration($events)
            ]
        ];
    }

    public function updateNotificationPreferences(User $parent, array $preferences): array
    {
        // Store preferences in user profile or separate table
        $parent->update([
            'transport_notification_preferences' => $preferences
        ]);

        return $preferences;
    }

    public function getNotifications(User $parent, array $filters = []): Collection
    {
        $query = TransportNotification::where('parent_id', $parent->id)
            ->with(['student']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('notification_type', $filters['type']);
        }

        $limit = $filters['limit'] ?? 20;

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function markNotificationAsRead(int $notificationId): void
    {
        $notification = TransportNotification::findOrFail($notificationId);
        $notification->markAsRead();
    }

    public function getRouteMap(Student $student): ?array
    {
        $subscription = $student->transportSubscriptions()
            ->where('status', 'active')
            ->with(['transportRoute.busStops', 'pickupStop', 'dropoffStop'])
            ->first();

        if (!$subscription) {
            return null;
        }

        $route = $subscription->transportRoute;

        return [
            'route' => $route,
            'waypoints' => $route->waypoints,
            'stops' => $route->busStops->map(function($stop) use ($subscription) {
                return [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'address' => $stop->address,
                    'coordinates' => [
                        'lat' => (float) $stop->latitude,
                        'lng' => (float) $stop->longitude
                    ],
                    'scheduled_time' => $stop->scheduled_arrival_time,
                    'is_pickup' => $stop->id === $subscription->pickup_stop_id,
                    'is_dropoff' => $stop->id === $subscription->dropoff_stop_id,
                    'type' => $this->getStopType($stop, $subscription)
                ];
            })
        ];
    }

    public function requestStopChange(Student $student, array $data): array
    {
        $subscription = $student->transportSubscriptions()
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            throw new \Exception('Student does not have an active transport subscription');
        }

        // This would integrate with the Forms Engine to create a change request
        $changeRequest = [
            'student_id' => $student->id,
            'current_subscription_id' => $subscription->id,
            'requested_changes' => $data,
            'status' => 'pending',
            'submitted_at' => now()
        ];

        // In a real implementation, this would create a form instance
        // and trigger the approval workflow

        return $changeRequest;
    }

    public function hasAccessToStudent(User $parent, Student $student): bool
    {
        // Check if the user is the parent/guardian of the student
        // through the family relationships table
        return $student->familyRelationships()
            ->where('guardian_user_id', $parent->id)
            ->where('status', 'active')
            ->exists();
    }

    private function getParentStudents(User $parent): Collection
    {
        // Get students where this user is listed as a parent/guardian
        // through the family relationships table
        return Student::whereHas('familyRelationships', function($query) use ($parent) {
            $query->where('guardian_user_id', $parent->id)
                  ->where('status', 'active');
        })->get();
    }

    private function getTodayEvents(Student $student): Collection
    {
        return StudentTransportEvent::where('student_id', $student->id)
            ->whereDate('event_timestamp', now())
            ->orderBy('event_timestamp')
            ->get();
    }

    private function getCurrentTransportStatus(Student $student): string
    {
        $todayEvents = $this->getTodayEvents($student);
        $busLocation = $this->getStudentBusLocation($student);

        return $this->determineCurrentStatus($todayEvents, $busLocation);
    }

    private function determineCurrentStatus(Collection $events, ?array $busLocation): string
    {
        $checkedIn = $events->where('event_type', 'check_in')->isNotEmpty();
        $checkedOut = $events->where('event_type', 'check_out')->isNotEmpty();

        if ($checkedOut) {
            return 'arrived_at_school';
        }

        if ($checkedIn) {
            if ($busLocation && $busLocation['is_moving']) {
                return 'on_bus_traveling';
            }
            return 'on_bus_waiting';
        }

        if ($busLocation) {
            return 'bus_approaching';
        }

        return 'waiting_for_pickup';
    }

    private function getEstimatedArrival(StudentTransportSubscription $subscription): ?string
    {
        $bus = $subscription->transportRoute->getCurrentBus();
        if (!$bus || !$bus->latestTracking) {
            return null;
        }

        // Calculate ETA to pickup stop
        $tracking = $bus->latestTracking;
        $pickupStop = $subscription->pickupStop;

        // Simple calculation - in reality would use more sophisticated routing
        $distance = $this->calculateDistance(
            $tracking->latitude, $tracking->longitude,
            $pickupStop->latitude, $pickupStop->longitude
        );

        $speed = max($tracking->speed_kmh, 20); // Minimum speed assumption
        $etaMinutes = ($distance / $speed) * 60;

        return now()->addMinutes($etaMinutes)->format('H:i');
    }

    private function getRecentNotifications(User $parent, int $limit): Collection
    {
        return $this->getNotifications($parent, ['limit' => $limit]);
    }

    private function getUnreadNotificationsCount(User $parent): int
    {
        return TransportNotification::where('parent_id', $parent->id)
            ->where('status', '!=', 'read')
            ->count();
    }

    private function calculateEtaToStop($tracking, $stop): ?int
    {
        $distance = $this->calculateDistance(
            $tracking->latitude, $tracking->longitude,
            $stop->latitude, $stop->longitude
        );

        $speed = max($tracking->speed_kmh, 20);
        return (int) round(($distance / $speed) * 60);
    }

    private function calculateOnTimeRate(Collection $events): float
    {
        // Implementation would compare actual vs scheduled times
        return 92.5; // Mock value
    }

    private function getFavoriteStop(Collection $events): ?string
    {
        $stopCounts = $events->where('event_type', 'check_in')
            ->groupBy('bus_stop_id')
            ->map(function($group) {
                return $group->count();
            })
            ->sortDesc();

        return $stopCounts->keys()->first();
    }

    private function getAverageTripDuration(Collection $events): float
    {
        // Calculate average time between check-in and check-out
        return 25.5; // Mock value in minutes
    }

    private function getStopType($stop, $subscription): string
    {
        if ($stop->id === $subscription->pickup_stop_id) {
            return 'pickup';
        }
        if ($stop->id === $subscription->dropoff_stop_id) {
            return 'dropoff';
        }
        return 'regular';
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }
}

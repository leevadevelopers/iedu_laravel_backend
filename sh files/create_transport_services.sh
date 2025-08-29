#!/bin/bash

# Transport Module - Services Generator
echo "⚙️ Creating Transport Module Services..."

# 1. TransportRouteService
cat > app/Services/V1/Transport/TransportRouteService.php << 'EOF'
<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\Transport\BusStop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TransportRouteService
{
    public function getRoutes(array $filters = []): LengthAwarePaginator
    {
        $query = TransportRoute::with(['busStops', 'busAssignments.fleetBus', 'studentSubscriptions'])
            ->withCount(['busStops', 'studentSubscriptions']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['shift'])) {
            $query->where('shift', $filters['shift']);
        }

        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('name')->paginate(15);
    }

    public function createRoute(array $data): TransportRoute
    {
        // Calculate estimated duration based on distance and average speed
        $estimatedDuration = $this->calculateEstimatedDuration($data['total_distance_km']);
        $data['estimated_duration_minutes'] = $estimatedDuration;

        $route = TransportRoute::create($data);

        // Log route creation
        activity()
            ->performedOn($route)
            ->log('Transport route created');

        return $route;
    }

    public function updateRoute(TransportRoute $route, array $data): TransportRoute
    {
        // Recalculate duration if distance changed
        if (isset($data['total_distance_km']) && $data['total_distance_km'] !== $route->total_distance_km) {
            $data['estimated_duration_minutes'] = $this->calculateEstimatedDuration($data['total_distance_km']);
        }

        $route->update($data);

        // Log route update
        activity()
            ->performedOn($route)
            ->log('Transport route updated');

        return $route->fresh();
    }

    public function deleteRoute(TransportRoute $route): bool
    {
        // Check if route has active assignments or subscriptions
        if ($route->busAssignments()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete route with active bus assignments');
        }

        if ($route->studentSubscriptions()->where('status', 'active')->exists()) {
            throw new \Exception('Cannot delete route with active student subscriptions');
        }

        activity()
            ->performedOn($route)
            ->log('Transport route deleted');

        return $route->delete();
    }

    public function getRouteDetails(TransportRoute $route): array
    {
        $route->load([
            'busStops' => function($query) {
                $query->orderBy('stop_order');
            },
            'busAssignments.fleetBus',
            'busAssignments.driver',
            'busAssignments.assistant',
            'studentSubscriptions.student'
        ]);

        return [
            'route' => $route,
            'current_bus' => $route->getCurrentBus(),
            'active_students_count' => $route->getActiveStudentCount(),
            'total_stops' => $route->busStops->count(),
            'is_operating_today' => $route->isOperatingToday(),
            'next_departure' => $this->getNextDepartureTime($route),
            'performance_metrics' => $this->getRoutePerformanceMetrics($route)
        ];
    }

    public function getActiveRoutes(): Collection
    {
        return TransportRoute::active()
            ->operatingToday()
            ->with(['busStops', 'busAssignments.fleetBus'])
            ->get();
    }

    public function optimizeRoute(TransportRoute $route): array
    {
        // Get all bus stops for the route
        $stops = $route->busStops()->orderBy('stop_order')->get();

        if ($stops->count() < 3) {
            throw new \Exception('Route must have at least 3 stops to optimize');
        }

        // Calculate optimal order using nearest neighbor algorithm
        $optimizedOrder = $this->calculateOptimalStopOrder($stops);

        // Update stop orders
        foreach ($optimizedOrder as $index => $stop) {
            $stop->update(['stop_order' => $index + 1]);
        }

        // Recalculate total distance
        $totalDistance = $this->calculateTotalDistance($optimizedOrder);
        $route->update(['total_distance_km' => $totalDistance]);

        return [
            'original_distance' => $route->getOriginal('total_distance_km'),
            'optimized_distance' => $totalDistance,
            'distance_saved' => $route->getOriginal('total_distance_km') - $totalDistance,
            'optimized_stops' => $optimizedOrder
        ];
    }

    public function createBusStop(TransportRoute $route, array $data): BusStop
    {
        // Set the next stop order
        $maxOrder = $route->busStops()->max('stop_order') ?? 0;
        $data['stop_order'] = $maxOrder + 1;
        $data['transport_route_id'] = $route->id;

        $stop = BusStop::create($data);

        // Update route distance and duration
        $this->recalculateRouteMetrics($route);

        activity()
            ->performedOn($stop)
            ->log('Bus stop created for route: ' . $route->name);

        return $stop;
    }

    public function updateBusStop(BusStop $stop, array $data): BusStop
    {
        $stop->update($data);

        // Recalculate route metrics if location changed
        if (isset($data['latitude']) || isset($data['longitude'])) {
            $this->recalculateRouteMetrics($stop->transportRoute);
        }

        activity()
            ->performedOn($stop)
            ->log('Bus stop updated');

        return $stop->fresh();
    }

    public function deleteBusStop(BusStop $stop): bool
    {
        // Check if stop has active subscriptions
        $hasActiveSubscriptions = $stop->pickupSubscriptions()->where('status', 'active')->exists() ||
                                 $stop->dropoffSubscriptions()->where('status', 'active')->exists();

        if ($hasActiveSubscriptions) {
            throw new \Exception('Cannot delete bus stop with active student subscriptions');
        }

        $route = $stop->transportRoute;

        activity()
            ->performedOn($stop)
            ->log('Bus stop deleted');

        $result = $stop->delete();

        // Recalculate route metrics
        $this->recalculateRouteMetrics($route);

        return $result;
    }

    private function calculateEstimatedDuration(float $distanceKm): int
    {
        // Assume average speed of 25 km/h for school bus routes (includes stops)
        $averageSpeedKmh = 25;
        $durationHours = $distanceKm / $averageSpeedKmh;

        return (int) round($durationHours * 60); // Convert to minutes
    }

    private function calculateOptimalStopOrder(Collection $stops): array
    {
        // Simple nearest neighbor algorithm
        $unvisited = $stops->toArray();
        $optimized = [];

        // Start with the first stop
        $current = array_shift($unvisited);
        $optimized[] = $current;

        while (!empty($unvisited)) {
            $nearest = null;
            $shortestDistance = PHP_FLOAT_MAX;
            $nearestIndex = -1;

            foreach ($unvisited as $index => $stop) {
                $distance = $this->calculateDistance(
                    $current['latitude'], $current['longitude'],
                    $stop['latitude'], $stop['longitude']
                );

                if ($distance < $shortestDistance) {
                    $shortestDistance = $distance;
                    $nearest = $stop;
                    $nearestIndex = $index;
                }
            }

            if ($nearest) {
                $optimized[] = $nearest;
                $current = $nearest;
                unset($unvisited[$nearestIndex]);
                $unvisited = array_values($unvisited); // Re-index array
            }
        }

        return $optimized;
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Haversine formula
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }

    private function calculateTotalDistance(array $stops): float
    {
        if (count($stops) < 2) {
            return 0;
        }

        $totalDistance = 0;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $totalDistance += $this->calculateDistance(
                $stops[$i]['latitude'], $stops[$i]['longitude'],
                $stops[$i + 1]['latitude'], $stops[$i + 1]['longitude']
            );
        }

        return round($totalDistance, 2);
    }

    private function recalculateRouteMetrics(TransportRoute $route): void
    {
        $stops = $route->busStops()->orderBy('stop_order')->get();
        $totalDistance = $this->calculateTotalDistance($stops->toArray());
        $estimatedDuration = $this->calculateEstimatedDuration($totalDistance);

        $route->update([
            'total_distance_km' => $totalDistance,
            'estimated_duration_minutes' => $estimatedDuration
        ]);
    }

    private function getNextDepartureTime(TransportRoute $route): ?string
    {
        if (!$route->isOperatingToday()) {
            return null;
        }

        $now = now();
        $departureTime = $now->copy()->setTimeFromTimeString($route->departure_time->format('H:i:s'));

        if ($departureTime->isPast()) {
            // Next departure is tomorrow
            $departureTime->addDay();
        }

        return $departureTime->diffForHumans();
    }

    private function getRoutePerformanceMetrics(TransportRoute $route): array
    {
        // Calculate performance metrics for the last 30 days
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'on_time_performance' => $this->calculateOnTimePerformance($route, $thirtyDaysAgo),
            'average_delay_minutes' => $this->calculateAverageDelay($route, $thirtyDaysAgo),
            'incidents_count' => $route->incidents()->where('incident_datetime', '>=', $thirtyDaysAgo)->count(),
            'student_satisfaction' => 4.2, // This would come from surveys
            'fuel_efficiency' => $this->calculateFuelEfficiency($route, $thirtyDaysAgo)
        ];
    }

    private function calculateOnTimePerformance(TransportRoute $route, $since): float
    {
        // Implementation would check actual vs scheduled arrival times
        // For now, return a mock value
        return 87.5;
    }

    private function calculateAverageDelay(TransportRoute $route, $since): float
    {
        // Implementation would calculate average delay from tracking data
        return 3.2;
    }

    private function calculateFuelEfficiency(TransportRoute $route, $since): float
    {
        // Implementation would calculate km per liter from daily logs
        return 8.5;
    }
}
EOF

# 2. FleetManagementService
cat > app/Services/V1/Transport/FleetManagementService.php << 'EOF'
<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusRouteAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FleetManagementService
{
    public function getBuses(array $filters = []): LengthAwarePaginator
    {
        $query = FleetBus::with(['currentAssignment.transportRoute', 'currentAssignment.driver'])
            ->withCount(['routeAssignments', 'transportEvents', 'incidents']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['make'])) {
            $query->where('make', 'like', '%' . $filters['make'] . '%');
        }

        if (isset($filters['model'])) {
            $query->where('model', 'like', '%' . $filters['model'] . '%');
        }

        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('license_plate', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('internal_code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('make', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('model', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('internal_code')->paginate(15);
    }

    public function createBus(array $data): FleetBus
    {
        $bus = FleetBus::create($data);

        // Log bus creation
        activity()
            ->performedOn($bus)
            ->log('Bus added to fleet');

        return $bus;
    }

    public function updateBus(FleetBus $bus, array $data): FleetBus
    {
        // If capacity is being reduced, check current occupancy
        if (isset($data['capacity']) && $data['capacity'] < $bus->current_capacity) {
            throw new \Exception('Cannot reduce capacity below current occupancy');
        }

        $bus->update($data);

        activity()
            ->performedOn($bus)
            ->log('Bus updated');

        return $bus->fresh();
    }

    public function deleteBus(FleetBus $bus): bool
    {
        // Check if bus has active assignments
        if ($bus->currentAssignment) {
            throw new \Exception('Cannot delete bus with active route assignment');
        }

        // Check if bus has recent transport events
        $hasRecentEvents = $bus->transportEvents()
            ->where('event_timestamp', '>=', now()->subDays(7))
            ->exists();

        if ($hasRecentEvents) {
            throw new \Exception('Cannot delete bus with recent transport events');
        }

        activity()
            ->performedOn($bus)
            ->log('Bus removed from fleet');

        return $bus->delete();
    }

    public function getBusDetails(FleetBus $bus): array
    {
        $bus->load([
            'currentAssignment.transportRoute.busStops',
            'currentAssignment.driver',
            'currentAssignment.assistant',
            'latestTracking',
            'incidents' => function($query) {
                $query->latest()->limit(5);
            }
        ]);

        return [
            'bus' => $bus,
            'current_location' => $bus->getLastKnownLocation(),
            'utilization_rate' => $bus->getUtilizationRate(),
            'needs_inspection' => $bus->needsInspection(),
            'maintenance_status' => $this->getMaintenanceStatus($bus),
            'performance_metrics' => $this->getBusPerformanceMetrics($bus),
            'recent_incidents' => $bus->incidents
        ];
    }

    public function assignBusToRoute(FleetBus $bus, array $data): BusRouteAssignment
    {
        // Check if bus is available
        if (!$bus->isAvailable()) {
            throw new \Exception('Bus is not available for assignment');
        }

        // Deactivate any existing assignment
        $bus->routeAssignments()
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        // Create new assignment
        $data['fleet_bus_id'] = $bus->id;
        $assignment = BusRouteAssignment::create($data);

        activity()
            ->performedOn($assignment)
            ->log('Bus assigned to route');

        return $assignment->load(['transportRoute', 'driver', 'assistant']);
    }

    public function getAvailableBuses(): Collection
    {
        return FleetBus::available()
            ->with(['latestTracking'])
            ->orderBy('internal_code')
            ->get();
    }

    public function setBusMaintenance(FleetBus $bus): FleetBus
    {
        // Deactivate current assignment
        if ($bus->currentAssignment) {
            $bus->currentAssignment->update(['status' => 'suspended']);
        }

        $bus->update(['status' => 'maintenance']);

        activity()
            ->performedOn($bus)
            ->log('Bus set to maintenance');

        return $bus->fresh();
    }

    public function getMaintenanceReport(): array
    {
        $buses = FleetBus::with(['currentAssignment.transportRoute'])
            ->get();

        return [
            'total_fleet_size' => $buses->count(),
            'active_buses' => $buses->where('status', 'active')->count(),
            'maintenance_buses' => $buses->where('status', 'maintenance')->count(),
            'out_of_service' => $buses->where('status', 'out_of_service')->count(),
            'needing_inspection' => $buses->filter(fn($bus) => $bus->needsInspection())->count(),
            'average_age' => $this->calculateAverageFleetAge($buses),
            'utilization_rates' => $this->calculateUtilizationRates($buses),
            'upcoming_inspections' => $this->getUpcomingInspections($buses),
            'insurance_renewals' => $this->getUpcomingInsuranceRenewals($buses)
        ];
    }

    public function scheduleInspection(FleetBus $bus, array $data): void
    {
        $bus->update([
            'last_inspection_date' => $data['inspection_date'],
            'next_inspection_due' => $data['next_due_date'] ?? now()->addMonths(6)
        ]);

        activity()
            ->performedOn($bus)
            ->log('Inspection scheduled');
    }

    public function updateInsurance(FleetBus $bus, array $data): void
    {
        $bus->update([
            'insurance_expiry' => $data['expiry_date']
        ]);

        activity()
            ->performedOn($bus)
            ->log('Insurance information updated');
    }

    private function getMaintenanceStatus(FleetBus $bus): array
    {
        $status = [];

        if ($bus->needsInspection()) {
            $status[] = [
                'type' => 'inspection',
                'priority' => 'high',
                'message' => 'Inspection due: ' . $bus->next_inspection_due->format('Y-m-d')
            ];
        }

        if ($bus->insurance_expiry && $bus->insurance_expiry <= now()->addDays(30)) {
            $status[] = [
                'type' => 'insurance',
                'priority' => 'critical',
                'message' => 'Insurance expires: ' . $bus->insurance_expiry->format('Y-m-d')
            ];
        }

        return $status;
    }

    private function getBusPerformanceMetrics(FleetBus $bus): array
    {
        // Calculate metrics for the last 30 days
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'trips_completed' => $this->getTripsCompleted($bus, $thirtyDaysAgo),
            'distance_traveled' => $this->getDistanceTraveled($bus, $thirtyDaysAgo),
            'fuel_consumption' => $this->getFuelConsumption($bus, $thirtyDaysAgo),
            'breakdown_incidents' => $bus->incidents()
                ->where('incident_type', 'breakdown')
                ->where('incident_datetime', '>=', $thirtyDaysAgo)
                ->count(),
            'average_delay' => $this->getAverageDelay($bus, $thirtyDaysAgo)
        ];
    }

    private function calculateAverageFleetAge(Collection $buses): float
    {
        $totalAge = $buses->sum(fn($bus) => now()->year - $bus->manufacture_year);
        return $buses->count() > 0 ? round($totalAge / $buses->count(), 1) : 0;
    }

    private function calculateUtilizationRates(Collection $buses): array
    {
        return [
            'average' => $buses->avg(fn($bus) => $bus->getUtilizationRate()),
            'high' => $buses->filter(fn($bus) => $bus->getUtilizationRate() > 80)->count(),
            'medium' => $buses->filter(fn($bus) => $bus->getUtilizationRate() >= 50 && $bus->getUtilizationRate() <= 80)->count(),
            'low' => $buses->filter(fn($bus) => $bus->getUtilizationRate() < 50)->count()
        ];
    }

    private function getUpcomingInspections(Collection $buses): Collection
    {
        return $buses->filter(fn($bus) => $bus->needsInspection())
            ->sortBy('next_inspection_due')
            ->take(10);
    }

    private function getUpcomingInsuranceRenewals(Collection $buses): Collection
    {
        return $buses->filter(fn($bus) =>
            $bus->insurance_expiry &&
            $bus->insurance_expiry <= now()->addDays(60)
        )->sortBy('insurance_expiry')->take(10);
    }

    private function getTripsCompleted(FleetBus $bus, $since): int
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->where('status', 'completed')
            ->count();
    }

    private function getDistanceTraveled(FleetBus $bus, $since): float
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->whereNotNull('odometer_start')
            ->whereNotNull('odometer_end')
            ->get()
            ->sum(fn($log) => $log->getDistanceTraveled()) ?? 0;
    }

    private function getFuelConsumption(FleetBus $bus, $since): float
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->whereNotNull('fuel_level_start')
            ->whereNotNull('fuel_level_end')
            ->get()
            ->sum(fn($log) => $log->getFuelConsumed()) ?? 0;
    }

    private function getAverageDelay(FleetBus $bus, $since): float
    {
        // This would calculate from tracking data
        return 2.5; // Mock value
    }
}
EOF

# 3. StudentTransportService
cat > app/Services/V1/Transport/StudentTransportService.php << 'EOF'
<?php

namespace App\Services\V1\Transport;

use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class StudentTransportService
{
    public function getSubscriptions(array $filters = []): LengthAwarePaginator
    {
        $query = StudentTransportSubscription::with([
            'student',
            'pickupStop',
            'dropoffStop',
            'transportRoute'
        ]);

        // Apply filters
        if (isset($filters['route_id'])) {
            $query->where('transport_route_id', $filters['route_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('student_number', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    public function createSubscription(array $data): StudentTransportSubscription
    {
        // Validate route capacity
        $route = \App\Models\Transport\TransportRoute::findOrFail($data['transport_route_id']);
        $currentBus = $route->getCurrentBus();

        if ($currentBus && $currentBus->current_capacity >= $currentBus->capacity) {
            throw new \Exception('Bus capacity exceeded for this route');
        }

        // Check for existing active subscription
        $existingSubscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        if ($existingSubscription) {
            throw new \Exception('Student already has an active transport subscription');
        }

        $subscription = StudentTransportSubscription::create($data);

        // Update bus capacity
        if ($currentBus) {
            $currentBus->increment('current_capacity');
        }

        activity()
            ->performedOn($subscription)
            ->log('Student subscribed to transport');

        return $subscription;
    }

    public function getSubscriptionDetails(StudentTransportSubscription $subscription): array
    {
        $subscription->load([
            'student.user',
            'pickupStop',
            'dropoffStop',
            'transportRoute.busStops',
            'transportEvents' => function($query) {
                $query->latest()->limit(10);
            }
        ]);

        return [
            'subscription' => $subscription,
            'recent_events' => $subscription->transportEvents,
            'qr_code_image' => $subscription->generateQrCodeImage(),
            'parent_contacts' => $this->getParentContacts($subscription->student),
            'attendance_stats' => $this->getAttendanceStats($subscription)
        ];
    }

    public function recordCheckin(array $data): StudentTransportEvent
    {
        // Validate subscription
        $subscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            throw new \Exception('Student does not have an active transport subscription');
        }

        // Check for duplicate checkin
        $existingCheckin = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_in')
            ->whereDate('event_timestamp', now())
            ->first();

        if ($existingCheckin) {
            throw new \Exception('Student already checked in today');
        }

        $eventData = array_merge($data, [
            'event_type' => 'check_in',
            'transport_route_id' => $subscription->transport_route_id,
            'event_timestamp' => now(),
            'recorded_by' => auth()->id(),
            'is_automated' => $data['validation_method'] !== 'manual'
        ]);

        $event = StudentTransportEvent::create($eventData);

        // Fire event
        event(new StudentCheckedIn($event));

        activity()
            ->performedOn($event)
            ->log('Student checked in to transport');

        return $event;
    }

    public function recordCheckout(array $data): StudentTransportEvent
    {
        // Validate that student checked in first
        $checkinEvent = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_in')
            ->whereDate('event_timestamp', now())
            ->first();

        if (!$checkinEvent) {
            throw new \Exception('Student must check in before checking out');
        }

        // Check for duplicate checkout
        $existingCheckout = StudentTransportEvent::where('student_id', $data['student_id'])
            ->where('event_type', 'check_out')
            ->whereDate('event_timestamp', now())
            ->first();

        if ($existingCheckout) {
            throw new \Exception('Student already checked out today');
        }

        $subscription = StudentTransportSubscription::where('student_id', $data['student_id'])
            ->where('status', 'active')
            ->first();

        $eventData = array_merge($data, [
            'event_type' => 'check_out',
            'transport_route_id' => $subscription->transport_route_id,
            'event_timestamp' => now(),
            'recorded_by' => auth()->id(),
            'is_automated' => $data['validation_method'] !== 'manual'
        ]);

        $event = StudentTransportEvent::create($eventData);

        // Fire event
        event(new StudentCheckedOut($event));

        activity()
            ->performedOn($event)
            ->log('Student checked out of transport');

        return $event;
    }

    public function getStudentHistory(Student $student): array
    {
        $events = StudentTransportEvent::where('student_id', $student->id)
            ->with(['fleetBus', 'busStop', 'transportRoute'])
            ->orderBy('event_timestamp', 'desc')
            ->limit(50)
            ->get();

        return [
            'recent_events' => $events,
            'total_trips' => $this->getTotalTrips($student),
            'attendance_rate' => $this->calculateAttendanceRate($student),
            'favorite_stop' => $this->getFavoriteStop($student),
            'monthly_stats' => $this->getMonthlyStats($student)
        ];
    }

    public function validateQrCode(string $qrCode): array
    {
        $subscription = StudentTransportSubscription::where('qr_code', $qrCode)
            ->where('status', 'active')
            ->with(['student', 'transportRoute'])
            ->first();

        if (!$subscription) {
            throw new \Exception('Invalid or inactive QR code');
        }

        return [
            'valid' => true,
            'student' => $subscription->student,
            'subscription' => $subscription,
            'route' => $subscription->transportRoute,
            'special_needs' => $subscription->special_needs
        ];
    }

    public function generateQrCode(StudentTransportSubscription $subscription): string
    {
        return $subscription->generateQrCodeImage();
    }

    public function getBusRoster(array $data): array
    {
        $date = $data['date'] ?? now()->toDateString();

        $subscriptions = StudentTransportSubscription::where('transport_route_id', $data['route_id'])
            ->where('status', 'active')
            ->with(['student', 'pickupStop', 'dropoffStop'])
            ->get();

        $events = StudentTransportEvent::where('fleet_bus_id', $data['bus_id'])
            ->where('transport_route_id', $data['route_id'])
            ->whereDate('event_timestamp', $date)
            ->get()
            ->groupBy('student_id');

        $roster = $subscriptions->map(function($subscription) use ($events) {
            $studentEvents = $events->get($subscription->student_id, collect());

            return [
                'student' => $subscription->student,
                'pickup_stop' => $subscription->pickupStop,
                'dropoff_stop' => $subscription->dropoffStop,
                'checked_in' => $studentEvents->where('event_type', 'check_in')->isNotEmpty(),
                'checked_out' => $studentEvents->where('event_type', 'check_out')->isNotEmpty(),
                'special_needs' => $subscription->special_needs,
                'events' => $studentEvents
            ];
        });

        return [
            'date' => $date,
            'total_students' => $roster->count(),
            'checked_in' => $roster->where('checked_in', true)->count(),
            'checked_out' => $roster->where('checked_out', true)->count(),
            'no_shows' => $roster->where('checked_in', false)->count(),
            'students' => $roster->values()
        ];
    }

    private function getParentContacts(Student $student): array
    {
        // This would get parent contacts from the student's family relationships
        return []; // Mock implementation
    }

    private function getAttendanceStats(StudentTransportSubscription $subscription): array
    {
        $totalDays = $subscription->created_at->diffInDays(now());
        $attendedDays = StudentTransportEvent::where('student_id', $subscription->student_id)
            ->where('event_type', 'check_in')
            ->where('event_timestamp', '>=', $subscription->created_at)
            ->distinct('event_timestamp::date')
            ->count();

        return [
            'total_days' => $totalDays,
            'attended_days' => $attendedDays,
            'attendance_rate' => $totalDays > 0 ? round(($attendedDays / $totalDays) * 100, 2) : 0
        ];
    }

    private function getTotalTrips(Student $student): int
    {
        return StudentTransportEvent::where('student_id', $student->id)
            ->where('event_type', 'check_in')
            ->count();
    }

    private function calculateAttendanceRate(Student $student): float
    {
        $subscription = $student->transportSubscriptions()->where('status', 'active')->first();
        if (!$subscription) return 0;

        $stats = $this->getAttendanceStats($subscription);
        return $stats['attendance_rate'];
    }

    private function getFavoriteStop(Student $student): ?array
    {
        $stopUsage = StudentTransportEvent::where('student_id', $student->id)
            ->where('event_type', 'check_in')
            ->with('busStop')
            ->get()
            ->groupBy('bus_stop_id')
            ->map->count()
            ->sortDesc()
            ->first();

        return $stopUsage ? ['stop' => $stopUsage, 'count' => $stopUsage] : null;
    }

    private function getMonthlyStats(Student $student): array
    {
        $stats = [];
        for ($i = 0; $i < 6; $i++) {
            $month = now()->subMonths($i);
            $tripCount = StudentTransportEvent::where('student_id', $student->id)
                ->where('event_type', 'check_in')
                ->whereYear('event_timestamp', $month->year)
                ->whereMonth('event_timestamp', $month->month)
                ->count();

            $stats[] = [
                'month' => $month->format('M Y'),
                'trips' => $tripCount
            ];
        }

        return array_reverse($stats);
    }
}
EOF

# 4. TransportTrackingService
cat > app/Services/V1/Transport/TransportTrackingService.php << 'EOF'
<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportTracking;
use App\Models\V1\Transport\BusStop;
use App\Events\V1\Transport\BusLocationUpdated;
use App\Events\V1\Transport\BusArrivedAtStop;
use Illuminate\Database\Eloquent\Collection;

class TransportTrackingService
{
    public function updateLocation(array $data): TransportTracking
    {
        // Create tracking record
        $tracking = TransportTracking::create(array_merge($data, [
            'tracked_at' => now()
        ]));

        // Calculate ETA to next stop
        if (isset($data['current_stop_id']) && !isset($data['next_stop_id'])) {
            $nextStop = $this->getNextStop($data['route_id'], $data['current_stop_id']);
            if ($nextStop) {
                $eta = $this->calculateEta($data['latitude'], $data['longitude'], $nextStop, $data['speed_kmh']);
                $tracking->update([
                    'next_stop_id' => $nextStop->id,
                    'eta_minutes' => $eta
                ]);
            }
        }

        // Check if bus arrived at a stop
        $this->checkStopArrival($tracking);

        // Fire location update event
        event(new BusLocationUpdated($tracking));

        return $tracking;
    }

    public function getCurrentLocation(FleetBus $bus): ?TransportTracking
    {
        return $bus->latestTracking;
    }

    public function getRouteProgress(array $data): array
    {
        $routeId = $data['route_id'];
        $date = $data['date'] ?? now()->toDateString();

        $route = \App\Models\Transport\TransportRoute::with(['busStops' => function($query) {
            $query->orderBy('stop_order');
        }])->findOrFail($routeId);

        $bus = $route->getCurrentBus();
        if (!$bus) {
            throw new \Exception('No bus assigned to this route');
        }

        $latestTracking = $bus->transportTracking()
            ->where('transport_route_id', $routeId)
            ->whereDate('tracked_at', $date)
            ->latest('tracked_at')
            ->first();

        return [
            'route' => $route,
            'bus' => $bus,
            'current_location' => $latestTracking,
            'progress_percentage' => $this->calculateProgressPercentage($route, $latestTracking),
            'stops_completed' => $this->getCompletedStops($route, $latestTracking),
            'estimated_completion' => $this->getEstimatedCompletionTime($route, $latestTracking)
        ];
    }

    public function calculateEta(int $busId, int $stopId): ?int
    {
        $bus = FleetBus::findOrFail($busId);
        $stop = BusStop::findOrFail($stopId);
        $latestLocation = $bus->latestTracking;

        if (!$latestLocation) {
            return null;
        }

        return $this->calculateEta(
            $latestLocation->latitude,
            $latestLocation->longitude,
            $stop,
            $latestLocation->speed_kmh
        );
    }

    public function getActiveBusesWithLocation(): Collection
    {
        return FleetBus::active()
            ->with(['latestTracking', 'currentAssignment.transportRoute'])
            ->whereHas('latestTracking', function($query) {
                $query->where('tracked_at', '>=', now()->subHours(2));
            })
            ->get();
    }

    public function getTrackingHistory(FleetBus $bus, array $filters = []): Collection
    {
        $query = $bus->transportTracking()
            ->orderBy('tracked_at', 'desc');

        if (isset($filters['date'])) {
            $query->whereDate('tracked_at', $filters['date']);
        } elseif (isset($filters['hours'])) {
            $query->where('tracked_at', '>=', now()->subHours($filters['hours']));
        } else {
            // Default to last 24 hours
            $query->where('tracked_at', '>=', now()->subDay());
        }

        return $query->limit(1000)->get();
    }

    public function generateGeofence(int $stopId, int $radiusMeters): array
    {
        $stop = BusStop::findOrFail($stopId);

        // Generate circular geofence
        $geofence = [
            'type' => 'circle',
            'center' => [
                'lat' => (float) $stop->latitude,
                'lng' => (float) $stop->longitude
            ],
            'radius' => $radiusMeters,
            'coordinates' => $this->generateCircleCoordinates(
                $stop->latitude,
                $stop->longitude,
                $radiusMeters
            )
        ];

        return $geofence;
    }

    public function isWithinGeofence(float $lat, float $lng, array $geofence): bool
    {
        if ($geofence['type'] === 'circle') {
            $distance = $this->calculateDistance(
                $lat, $lng,
                $geofence['center']['lat'], $geofence['center']['lng']
            ) * 1000; // Convert to meters

            return $distance <= $geofence['radius'];
        }

        return false;
    }

    public function getBusSpeed(FleetBus $bus): ?float
    {
        return $bus->latestTracking?->speed_kmh;
    }

    public function getBusStatus(FleetBus $bus): string
    {
        $tracking = $bus->latestTracking;

        if (!$tracking) {
            return 'offline';
        }

        if ($tracking->tracked_at < now()->subMinutes(10)) {
            return 'offline';
        }

        return $tracking->status ?? 'unknown';
    }

    private function getNextStop(int $routeId, int $currentStopId): ?BusStop
    {
        $currentStop = BusStop::findOrFail($currentStopId);

        return BusStop::where('transport_route_id', $routeId)
            ->where('stop_order', '>', $currentStop->stop_order)
            ->orderBy('stop_order')
            ->first();
    }

    private function calculateEta(float $fromLat, float $fromLng, BusStop $stop, float $speedKmh): int
    {
        $distance = $this->calculateDistance($fromLat, $fromLng, $stop->latitude, $stop->longitude);

        if ($speedKmh <= 0) {
            $speedKmh = 25; // Default speed
        }

        $etaHours = $distance / $speedKmh;
        return (int) round($etaHours * 60); // Convert to minutes
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

    private function checkStopArrival(TransportTracking $tracking): void
    {
        $route = $tracking->transportRoute;
        $stops = $route->busStops;

        foreach ($stops as $stop) {
            $geofence = $this->generateGeofence($stop->id, 100); // 100m radius

            if ($this->isWithinGeofence($tracking->latitude, $tracking->longitude, $geofence)) {
                // Update tracking status
                $tracking->update([
                    'status' => 'at_stop',
                    'current_stop_id' => $stop->id
                ]);

                // Fire arrival event
                event(new BusArrivedAtStop($tracking, $stop));
                break;
            }
        }
    }

    private function calculateProgressPercentage($route, $tracking): float
    {
        if (!$tracking || !$tracking->current_stop_id) {
            return 0;
        }

        $currentStop = BusStop::find($tracking->current_stop_id);
        if (!$currentStop) {
            return 0;
        }

        $totalStops = $route->busStops->count();
        return $totalStops > 0 ? ($currentStop->stop_order / $totalStops) * 100 : 0;
    }

    private function getCompletedStops($route, $tracking): int
    {
        if (!$tracking || !$tracking->current_stop_id) {
            return 0;
        }

        $currentStop = BusStop::find($tracking->current_stop_id);
        return $currentStop ? $currentStop->stop_order - 1 : 0;
    }

    private function getEstimatedCompletionTime($route, $tracking): ?string
    {
        if (!$tracking) {
            return null;
        }

        $remainingStops = $route->busStops->where('stop_order', '>', $tracking->currentStop?->stop_order ?? 0)->count();
        $estimatedMinutes = $remainingStops * 5; // Assume 5 minutes per stop

        return now()->addMinutes($estimatedMinutes)->format('H:i');
    }

    private function generateCircleCoordinates(float $lat, float $lng, int $radiusMeters): array
    {
        $coordinates = [];
        $earthRadius = 6371000; // meters

        for ($i = 0; $i <= 360; $i += 10) {
            $angle = deg2rad($i);

            $deltaLat = ($radiusMeters * cos($angle)) / $earthRadius;
            $deltaLng = ($radiusMeters * sin($angle)) / ($earthRadius * cos(deg2rad($lat)));

            $coordinates[] = [
                'lat' => $lat + rad2deg($deltaLat),
                'lng' => $lng + rad2deg($deltaLng)
            ];
        }

        return $coordinates;
    }
}
EOF

# 5. ParentPortalService
cat > app/Services/V1/Transport/ParentPortalService.php << 'EOF'
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
        // This would check the family relationships
        return true; // Simplified for this example
    }

    private function getParentStudents(User $parent): Collection
    {
        // Get students where this user is listed as a parent/guardian
        // This would query the family relationships
        return collect(); // Simplified for this example
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
            ->map->count()
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
EOF

echo "✅ Transport module services created successfully!"
echo "📝 Services include comprehensive business logic for transport operations."

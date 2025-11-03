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
            $query->where(function ($q) use ($filters) {
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
            'busStops' => function ($query) {
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

        // Store original distance
        $originalDistance = $route->total_distance_km;

        // Calculate optimal order using nearest neighbor algorithm
        $optimizedStops = $this->calculateOptimalStopOrder($stops);

        // Update stop orders (now $stop is a BusStop model)
        foreach ($optimizedStops as $index => $stop) {
            $stop->update(['stop_order' => $index + 1]);
        }

        // Recalculate total distance
        $stopsArray = $optimizedStops->map(function ($stop) {
            return [
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude
            ];
        })->toArray();

        $totalDistance = $this->calculateTotalDistance($stopsArray);
        $route->update([
            'total_distance_km' => $totalDistance,
            'estimated_duration_minutes' => $this->calculateEstimatedDuration($totalDistance)
        ]);

        return [
            'original_distance' => $originalDistance,
            'optimized_distance' => $totalDistance,
            'distance_saved' => round($originalDistance - $totalDistance, 2),
            'percentage_saved' => $originalDistance > 0
                ? round((($originalDistance - $totalDistance) / $originalDistance) * 100, 2)
                : 0,
            'total_stops' => $optimizedStops->count(),
            'optimized_stops' => $optimizedStops->map(function ($stop, $index) {
                return [
                    'id' => $stop->id,
                    'name' => $stop->name,
                    'code' => $stop->code,
                    'new_order' => $index + 1,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude
                ];
            })
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


    private function calculateOptimalStopOrder(Collection $stops): Collection
    {
        // Simple nearest neighbor algorithm
        $unvisited = $stops->values(); // Keep as collection
        $optimized = [];

        // Start with the first stop
        $current = $unvisited->shift();
        $optimized[] = $current;

        while ($unvisited->isNotEmpty()) {
            $nearest = null;
            $shortestDistance = PHP_FLOAT_MAX;
            $nearestKey = null;

            foreach ($unvisited as $key => $stop) {
                $distance = $this->calculateDistance(
                    $current->latitude,
                    $current->longitude,
                    $stop->latitude,
                    $stop->longitude
                );

                if ($distance < $shortestDistance) {
                    $shortestDistance = $distance;
                    $nearest = $stop;
                    $nearestKey = $key;
                }
            }

            if ($nearest) {
                $optimized[] = $nearest;
                $current = $nearest;
                $unvisited->forget($nearestKey);
            }
        }

        // Convert array back to Eloquent Collection
        return new Collection($optimized);
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Haversine formula
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

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
                $stops[$i]['latitude'],
                $stops[$i]['longitude'],
                $stops[$i + 1]['latitude'],
                $stops[$i + 1]['longitude']
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

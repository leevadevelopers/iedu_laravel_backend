<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportTracking;
use App\Models\V1\Transport\BusStop;
use App\Events\V1\Transport\BusLocationUpdated;
use App\Events\V1\Transport\BusArrivedAtStop;
use App\Models\V1\Transport\TransportRoute;
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

        $route = TransportRoute::with(['busStops' => function($query) {
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

    // private function calculateEta(float $fromLat, float $fromLng, BusStop $stop, float $speedKmh): int
    // {
    //     $distance = $this->calculateDistance($fromLat, $fromLng, $stop->latitude, $stop->longitude);

    //     if ($speedKmh <= 0) {
    //         $speedKmh = 25; // Default speed
    //     }

    //     $etaHours = $distance / $speedKmh;
    //     return (int) round($etaHours * 60); // Convert to minutes
    // }


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

<?php

namespace App\Jobs\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportTracking;
use App\Services\V1\Transport\TransportTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGpsTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 2;

    protected $gpsData;

    public function __construct(array $gpsData)
    {
        $this->gpsData = $gpsData;
    }

    public function handle(TransportTrackingService $trackingService)
    {
        try {
            // Validate GPS data
            $this->validateGpsData();

            // Find the bus by GPS device ID or other identifier
            $bus = $this->findBusFromGpsData();

            if (!$bus) {
                Log::warning('GPS data received for unknown bus', $this->gpsData);
                return;
            }

            // Get current route assignment
            $currentAssignment = $bus->currentAssignment;
            if (!$currentAssignment) {
                Log::info('GPS data received for bus without active route assignment', [
                    'bus_id' => $bus->id,
                    'gps_data' => $this->gpsData
                ]);
                return;
            }

            // Process the location update
            $trackingData = $this->prepareTrackingData($bus, $currentAssignment);
            $trackingService->updateLocation($trackingData);

            // Check for geofence events (arrival at stops)
            $this->checkGeofenceEvents($bus, $trackingData);

            // Update bus status based on movement
            $this->updateBusStatus($bus);

        } catch (\Exception $e) {
            Log::error('GPS tracking processing failed', [
                'error' => $e->getMessage(),
                'gps_data' => $this->gpsData
            ]);

            $this->fail($e);
        }
    }

    private function validateGpsData(): void
    {
        $required = ['latitude', 'longitude', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($this->gpsData[$field])) {
                throw new \InvalidArgumentException("Missing required GPS field: {$field}");
            }
        }

        if ($this->gpsData['latitude'] < -90 || $this->gpsData['latitude'] > 90) {
            throw new \InvalidArgumentException('Invalid latitude value');
        }

        if ($this->gpsData['longitude'] < -180 || $this->gpsData['longitude'] > 180) {
            throw new \InvalidArgumentException('Invalid longitude value');
        }
    }

    private function findBusFromGpsData(): ?FleetBus
    {
        // Try multiple methods to identify the bus
        if (isset($this->gpsData['device_id'])) {
            return FleetBus::where('gps_device_id', $this->gpsData['device_id'])->first();
        }

        if (isset($this->gpsData['bus_id'])) {
            return FleetBus::find($this->gpsData['bus_id']);
        }

        if (isset($this->gpsData['license_plate'])) {
            return FleetBus::where('license_plate', $this->gpsData['license_plate'])->first();
        }

        return null;
    }

    private function prepareTrackingData(FleetBus $bus, $assignment): array
    {
        return [
            'bus_id' => $bus->id,
            'route_id' => $assignment->transport_route_id,
            'latitude' => $this->gpsData['latitude'],
            'longitude' => $this->gpsData['longitude'],
            'speed_kmh' => $this->gpsData['speed'] ?? 0,
            'heading' => $this->gpsData['heading'] ?? null,
            'altitude' => $this->gpsData['altitude'] ?? null,
            'status' => $this->determineStatus(),
            'raw_gps_data' => $this->gpsData
        ];
    }

    private function determineStatus(): string
    {
        $speed = $this->gpsData['speed'] ?? 0;

        if ($speed < 1) {
            return 'stationary';
        } elseif ($speed < 10) {
            return 'at_stop';
        } else {
            return 'in_transit';
        }
    }

    private function checkGeofenceEvents(FleetBus $bus, array $trackingData): void
    {
        // This would check if the bus has entered any stop geofences
        // Implementation would compare current location with stop locations
        // and trigger BusArrivedAtStop events if within geofence
    }

    private function updateBusStatus(FleetBus $bus): void
    {
        $lastTracking = $bus->latestTracking;

        if ($lastTracking && $lastTracking->tracked_at < now()->subMinutes(10)) {
            // Bus hasn't reported in 10 minutes - might be offline
            Log::warning('Bus GPS tracking appears offline', [
                'bus_id' => $bus->id,
                'last_tracking' => $lastTracking->tracked_at
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('GPS tracking job failed permanently', [
            'exception' => $exception->getMessage(),
            'gps_data' => $this->gpsData
        ]);
    }
}

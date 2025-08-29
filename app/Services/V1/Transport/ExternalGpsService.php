<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportTracking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ExternalGpsService
{
    protected $apiKey;
    protected $baseUrl;
    protected $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.external_gps.api_key');
        $this->baseUrl = config('services.external_gps.base_url');
        $this->timeout = config('services.external_gps.timeout', 30);
    }

    /**
     * Get latest GPS location for a device
     */
    public function getLatestLocation(string $deviceId): ?array
    {
        try {
            // Check cache first
            $cacheKey = "gps_location_{$deviceId}";
            $cachedData = Cache::get($cacheKey);

            if ($cachedData && $this->isCacheValid($cachedData)) {
                Log::info('Using cached GPS data', ['device_id' => $deviceId]);
                return $cachedData;
            }

            // Fetch from external API
            $gpsData = $this->fetchFromExternalApi($deviceId);

            if ($gpsData) {
                // Cache the data for 30 seconds
                Cache::put($cacheKey, $gpsData, 30);
                return $gpsData;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get GPS location', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get GPS data for multiple devices
     */
    public function getBulkLocations(array $deviceIds): array
    {
        $locations = [];

        foreach ($deviceIds as $deviceId) {
            $location = $this->getLatestLocation($deviceId);
            if ($location) {
                $locations[$deviceId] = $location;
            }
        }

        return $locations;
    }

    /**
     * Get GPS history for a device
     */
    public function getLocationHistory(string $deviceId, Carbon $startTime, Carbon $endTime): array
    {
        try {
            $endpoint = "/devices/{$deviceId}/history";
            $params = [
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'limit' => 1000
            ];

            $response = $this->makeApiRequest($endpoint, $params);

            if ($response && isset($response['data'])) {
                return $response['data'];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to get GPS history', [
                'device_id' => $deviceId,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get device status and health information
     */
    public function getDeviceStatus(string $deviceId): ?array
    {
        try {
            $endpoint = "/devices/{$deviceId}/status";
            $response = $this->makeApiRequest($endpoint);

            if ($response && isset($response['status'])) {
                return [
                    'device_id' => $deviceId,
                    'status' => $response['status'],
                    'battery_level' => $response['battery_level'] ?? null,
                    'signal_strength' => $response['signal_strength'] ?? null,
                    'last_seen' => $response['last_seen'] ?? null,
                    'firmware_version' => $response['firmware_version'] ?? null
                ];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get device status', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send command to GPS device
     */
    public function sendDeviceCommand(string $deviceId, string $command, array $parameters = []): bool
    {
        try {
            $endpoint = "/devices/{$deviceId}/commands";
            $payload = [
                'command' => $command,
                'parameters' => $parameters,
                'timestamp' => now()->toISOString()
            ];

            $response = $this->makeApiRequest($endpoint, [], 'POST', $payload);

            if ($response && isset($response['success'])) {
                Log::info('Device command sent successfully', [
                    'device_id' => $deviceId,
                    'command' => $command,
                    'response' => $response
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send device command', [
                'device_id' => $deviceId,
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate GPS data format
     */
    public function validateGpsData(array $gpsData): bool
    {
        $requiredFields = ['latitude', 'longitude', 'timestamp', 'speed', 'heading'];

        foreach ($requiredFields as $field) {
            if (!isset($gpsData[$field])) {
                Log::warning('GPS data missing required field', [
                    'field' => $field,
                    'data' => $gpsData
                ]);
                return false;
            }
        }

        // Validate latitude range
        if (!is_numeric($gpsData['latitude']) || $gpsData['latitude'] < -90 || $gpsData['latitude'] > 90) {
            return false;
        }

        // Validate longitude range
        if (!is_numeric($gpsData['longitude']) || $gpsData['longitude'] < -180 || $gpsData['longitude'] > 180) {
            return false;
        }

        // Validate speed (should be non-negative)
        if (!is_numeric($gpsData['speed']) || $gpsData['speed'] < 0) {
            return false;
        }

        return true;
    }

    /**
     * Transform external GPS data to internal format
     */
    public function transformGpsData(array $externalData, FleetBus $bus): array
    {
        return [
            'school_id' => $bus->school_id,
            'fleet_bus_id' => $bus->id,
            'transport_route_id' => $bus->currentAssignment?->transport_route_id,
            'latitude' => $externalData['latitude'],
            'longitude' => $externalData['longitude'],
            'speed_kmh' => $this->convertSpeedToKmh($externalData['speed']),
            'heading' => $externalData['heading'] ?? null,
            'altitude' => $externalData['altitude'] ?? null,
            'accuracy' => $externalData['accuracy'] ?? null,
            'status' => $this->determineBusStatus($externalData),
            'tracked_at' => Carbon::parse($externalData['timestamp']),
            'metadata' => [
                'external_device_id' => $externalData['device_id'] ?? null,
            ]
        ];
    }

    /**
     * Fetch GPS data from external API
     */
    protected function fetchFromExternalApi(string $deviceId): ?array
    {
        try {
            $endpoint = "/devices/{$deviceId}/location";
            $response = $this->makeApiRequest($endpoint);

            if ($response && isset($response['location'])) {
                $gpsData = $response['location'];
                $gpsData['device_id'] = $deviceId;

                // Validate the data
                if ($this->validateGpsData($gpsData)) {
                    return $gpsData;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch GPS data from external API', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Make API request to external GPS service
     */
    protected function makeApiRequest(string $endpoint, array $params = [], string $method = 'GET', array $payload = []): ?array
    {
        try {
            $url = $this->baseUrl . $endpoint;

            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]);

            if ($method === 'GET') {
                $response = $request->get($url, $params);
            } else {
                $response = $request->post($url, $payload);
            }

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning('External GPS API request failed', [
                    'url' => $url,
                    'method' => $method,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('External GPS API request exception', [
                'url' => $url ?? 'unknown',
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if cached data is still valid
     */
    protected function isCacheValid(array $cachedData): bool
    {
        if (!isset($cachedData['timestamp'])) {
            return false;
        }

        $cachedTime = Carbon::parse($cachedData['timestamp']);
        $maxAge = config('services.external_gps.cache_max_age', 30); // seconds

        return $cachedTime->diffInSeconds(now()) < $maxAge;
    }

    /**
     * Convert speed to km/h
     */
    protected function convertSpeedToKmh($speed): float
    {
        // Assume speed is in m/s, convert to km/h
        if (is_numeric($speed)) {
            return round(($speed * 3.6), 2);
        }

        return 0.0;
    }

    /**
     * Determine bus status based on GPS data
     */
    protected function determineBusStatus(array $gpsData): string
    {
        $speed = $gpsData['speed'] ?? 0;

        if ($speed > 0) {
            return 'moving';
        } elseif ($speed === 0) {
            return 'stopped';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get API health status
     */
    public function getApiHealth(): array
    {
        try {
            $response = $this->makeApiRequest('/health');

            if ($response && isset($response['status'])) {
                return [
                    'status' => $response['status'],
                    'timestamp' => now()->toISOString(),
                    'response_time' => $response['response_time'] ?? null,
                    'version' => $response['version'] ?? null
                ];
            }

            return [
                'status' => 'unknown',
                'timestamp' => now()->toISOString(),
                'error' => 'Invalid response format'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to external GPS service
     */
    public function testConnection(): bool
    {
        try {
            $health = $this->getApiHealth();
            return $health['status'] === 'healthy';
        } catch (\Exception $e) {
            return false;
        }
    }
}

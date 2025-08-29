<?php

namespace App\Jobs\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Services\V1\Transport\ExternalGpsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncExternalGpsData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $tries = 3;

    protected $schoolId;

    public function __construct(int $schoolId = null)
    {
        $this->schoolId = $schoolId;
    }

    public function handle(ExternalGpsService $gpsService)
    {
        try {
            Log::info('Starting GPS data sync', ['school_id' => $this->schoolId]);

            // Get buses that need GPS sync
            $buses = $this->getBusesForSync();

            foreach ($buses as $bus) {
                $this->syncBusGpsData($bus, $gpsService);
            }

            Log::info('GPS data sync completed', [
                'school_id' => $this->schoolId,
                'buses_processed' => $buses->count()
            ]);

        } catch (\Exception $e) {
            Log::error('GPS data sync failed', [
                'school_id' => $this->schoolId,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function getBusesForSync()
    {
        $query = FleetBus::active()
            ->whereNotNull('gps_device_id')
            ->whereHas('currentAssignment');

        if ($this->schoolId) {
            $query->where('school_id', $this->schoolId);
        }

        return $query->get();
    }

    private function syncBusGpsData(FleetBus $bus, ExternalGpsService $gpsService): void
    {
        try {
            // Fetch latest GPS data from external service
            $gpsData = $gpsService->getLatestLocation($bus->gps_device_id);

            if (!$gpsData) {
                Log::warning('No GPS data available for bus', [
                    'bus_id' => $bus->id,
                    'device_id' => $bus->gps_device_id
                ]);
                return;
            }

            // Process the GPS data
            ProcessGpsTracking::dispatch($gpsData);

        } catch (\Exception $e) {
            Log::error('Failed to sync GPS data for bus', [
                'bus_id' => $bus->id,
                'device_id' => $bus->gps_device_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SyncExternalGpsData job failed permanently', [
            'school_id' => $this->schoolId,
            'exception' => $exception->getMessage()
        ]);
    }
}

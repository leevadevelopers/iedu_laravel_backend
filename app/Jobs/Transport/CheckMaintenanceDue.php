<?php

namespace App\Jobs\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Events\V1\Transport\BusMaintenanceScheduled;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckMaintenanceDue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public function handle()
    {
        try {
            Log::info('Starting maintenance due check');

            // Find buses needing maintenance
            $busesNeedingMaintenance = FleetBus::active()
                ->needingInspection()
                ->get();

            foreach ($busesNeedingMaintenance as $bus) {
                $this->processMaintenanceDue($bus);
            }

            // Check insurance renewals
            $this->checkInsuranceRenewals();

            Log::info('Maintenance check completed', [
                'buses_needing_maintenance' => $busesNeedingMaintenance->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Maintenance check failed', [
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function processMaintenanceDue(FleetBus $bus): void
    {
        // Create maintenance alert
        event(new BusMaintenanceScheduled($bus, 'inspection', $bus->next_inspection_due));

        // If overdue, set bus to maintenance status
        if ($bus->next_inspection_due && $bus->next_inspection_due->isPast()) {
            $bus->update(['status' => 'maintenance']);

            // Deactivate current assignment
            if ($bus->currentAssignment) {
                $bus->currentAssignment->update(['status' => 'suspended']);
            }

            Log::warning('Bus set to maintenance due to overdue inspection', [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'due_date' => $bus->next_inspection_due->format('Y-m-d')
            ]);
        }
    }

    private function checkInsuranceRenewals(): void
    {
        $busesWithExpiringInsurance = FleetBus::active()
            ->whereNotNull('insurance_expiry')
            ->where('insurance_expiry', '<=', now()->addDays(30))
            ->get();

        foreach ($busesWithExpiringInsurance as $bus) {
            Log::warning('Bus insurance expiring soon', [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'expiry_date' => $bus->insurance_expiry->format('Y-m-d')
            ]);

            // Send notification to administrators
            // This could integrate with the notification system
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('CheckMaintenanceDue job failed', [
            'exception' => $exception->getMessage()
        ]);
    }
}

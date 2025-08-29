<?php

namespace App\Console\Commands\Transport;

use App\Jobs\Transport\CheckMaintenanceDue;
use App\Jobs\Transport\SyncExternalGpsData;
use App\Jobs\Transport\ProcessTransportSubscription;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Console\Command;

class ProcessTransportJobs extends Command
{
    protected $signature = 'transport:process-jobs
                           {--school= : Process jobs for specific school ID}
                           {--maintenance : Check maintenance due}
                           {--gps-sync : Sync GPS data}
                           {--subscriptions : Process subscription renewals}';

    protected $description = 'Process various transport-related background jobs';

    public function handle()
    {
        $schoolId = $this->option('school');

        if ($this->option('maintenance')) {
            $this->info('Dispatching maintenance checks...');
            CheckMaintenanceDue::dispatch();
        }

        if ($this->option('gps-sync')) {
            $this->info('Dispatching GPS sync jobs...');
            SyncExternalGpsData::dispatch($schoolId);
        }

        if ($this->option('subscriptions')) {
            $this->info('Processing subscription renewals...');
            $this->processSubscriptionRenewals($schoolId);
        }

        // If no specific option, run all
        if (!$this->option('maintenance') && !$this->option('gps-sync') && !$this->option('subscriptions')) {
            $this->info('Running all transport jobs...');
            CheckMaintenanceDue::dispatch();
            SyncExternalGpsData::dispatch($schoolId);
            $this->processSubscriptionRenewals($schoolId);
        }

        $this->info('Transport jobs dispatched successfully!');
    }

    private function processSubscriptionRenewals($schoolId): void
    {
        $query = StudentTransportSubscription::where('status', 'active')
            ->where('auto_renewal', true)
            ->whereDate('end_date', '<=', now()->addDays(7)); // Renew 7 days before expiry

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $subscriptions = $query->get();

        foreach ($subscriptions as $subscription) {
            ProcessTransportSubscription::dispatch($subscription->id, 'renew');
        }

        $this->info("Dispatched {$subscriptions->count()} subscription renewal jobs");
    }
}

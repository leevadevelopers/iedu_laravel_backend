<?php

namespace App\Providers;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use App\Events\V1\Transport\BusLocationUpdated;
use App\Events\V1\Transport\BusArrivedAtStop;
use App\Events\V1\Transport\TransportIncidentCreated;
use App\Events\V1\Transport\BusMaintenanceScheduled;
use App\Events\V1\Transport\RouteOptimized;

use App\Listeners\V1\Transport\SendStudentCheckinNotification;
use App\Listeners\V1\Transport\SendStudentCheckoutNotification;
use App\Listeners\V1\Transport\UpdateBusCapacity;
use App\Listeners\V1\Transport\ProcessBusDelayAlert;
use App\Listeners\V1\Transport\HandleTransportIncident;
use App\Listeners\V1\Transport\LogTransportActivity;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class TransportEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        StudentCheckedIn::class => [
            SendStudentCheckinNotification::class,
            UpdateBusCapacity::class,
            LogTransportActivity::class,
        ],

        StudentCheckedOut::class => [
            SendStudentCheckoutNotification::class,
            UpdateBusCapacity::class,
            LogTransportActivity::class,
        ],

        BusLocationUpdated::class => [
            LogTransportActivity::class,
        ],

        BusArrivedAtStop::class => [
            ProcessBusDelayAlert::class,
        ],

        TransportIncidentCreated::class => [
            HandleTransportIncident::class,
            LogTransportActivity::class,
        ],

        BusMaintenanceScheduled::class => [
            // Add maintenance-related listeners here
        ],

        RouteOptimized::class => [
            // Add route optimization listeners here
        ],
    ];

    public function boot()
    {
        //
    }
}

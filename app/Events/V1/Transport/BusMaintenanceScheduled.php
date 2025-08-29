<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BusMaintenanceScheduled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $bus;
    public $maintenanceType;
    public $scheduledDate;

    public function __construct(FleetBus $bus, string $maintenanceType, $scheduledDate)
    {
        $this->bus = $bus;
        $this->maintenanceType = $maintenanceType;
        $this->scheduledDate = $scheduledDate;
    }
}

<?php

namespace App\Events\V1\Transport;

use App\Models\V1\Transport\TransportRoute;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteOptimized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $route;
    public $optimizationResults;

    public function __construct(TransportRoute $route, array $optimizationResults)
    {
        $this->route = $route;
        $this->optimizationResults = $optimizationResults;
    }
}

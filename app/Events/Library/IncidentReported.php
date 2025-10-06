<?php

namespace App\Events\Library;

use App\Models\V1\Library\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Incident $incident)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->incident->tenant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'incident.reported';
    }
}

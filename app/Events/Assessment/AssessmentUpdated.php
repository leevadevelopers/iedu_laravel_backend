<?php

namespace App\Events\Assessment;

use App\Models\Assessment\Assessment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Assessment $assessment
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('tenant.' . $this->assessment->tenant_id),
            new Channel('class.' . $this->assessment->class_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'assessment.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->assessment->id,
            'title' => $this->assessment->title,
            'status' => $this->assessment->status,
        ];
    }
}


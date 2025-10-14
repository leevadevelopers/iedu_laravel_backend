<?php

namespace App\Events\Assessment;

use App\Models\Assessment\Assessment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class GradesPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Assessment $assessment,
        public Collection $gradeEntries
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
        return 'grades.published';
    }

    public function broadcastWith(): array
    {
        return [
            'assessment_id' => $this->assessment->id,
            'assessment_title' => $this->assessment->title,
            'grades_count' => $this->gradeEntries->count(),
        ];
    }
}


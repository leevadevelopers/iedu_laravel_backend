<?php

namespace App\Events\Assessment;

use App\Models\Assessment\GradeEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GradeEntered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GradeEntry $gradeEntry
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->gradeEntry->student_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'grade.entered';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->gradeEntry->id,
            'assessment_id' => $this->gradeEntry->assessment_id,
            'marks_awarded' => $this->gradeEntry->marks_awarded,
            'is_published' => $this->gradeEntry->is_published,
        ];
    }
}


<?php

namespace App\Events\Assessment;

use App\Models\Assessment\GradeReview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GradeReviewRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GradeReview $gradeReview
    ) {}

    public function broadcastOn(): array
    {
        $gradeEntry = $this->gradeReview->gradeEntry;
        $assessment = $gradeEntry->assessment;
        
        return [
            new PrivateChannel('user.' . $assessment->teacher_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'grade.review.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->gradeReview->id,
            'grade_entry_id' => $this->gradeReview->grade_entry_id,
            'requester_id' => $this->gradeReview->requester_id,
            'status' => $this->gradeReview->status,
        ];
    }
}


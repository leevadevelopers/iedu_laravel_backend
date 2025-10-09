<?php

namespace App\Events\Assessment;

use App\Models\Assessment\GradeReview;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GradeReviewResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public GradeReview $gradeReview
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->gradeReview->requester_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'grade.review.resolved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->gradeReview->id,
            'status' => $this->gradeReview->status,
            'revised_marks' => $this->gradeReview->revised_marks,
        ];
    }
}


<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\GradeReviewResolved;
use App\Notifications\Assessment\GradeReviewResolvedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendGradeReviewResolvedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(GradeReviewResolved $event): void
    {
        $gradeReview = $event->gradeReview;
        $requester = $gradeReview->requester;
        
        // Notify the requester
        $requester->notify(new GradeReviewResolvedNotification($gradeReview));
    }
}


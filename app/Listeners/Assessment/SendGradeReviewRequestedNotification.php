<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\GradeReviewRequested;
use App\Notifications\Assessment\GradeReviewRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendGradeReviewRequestedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(GradeReviewRequested $event): void
    {
        $gradeReview = $event->gradeReview;
        $gradeEntry = $gradeReview->gradeEntry;
        $assessment = $gradeEntry->assessment;
        $teacher = $assessment->teacher;
        
        // Notify the teacher
        $teacher->notify(new GradeReviewRequestedNotification($gradeReview));
    }
}


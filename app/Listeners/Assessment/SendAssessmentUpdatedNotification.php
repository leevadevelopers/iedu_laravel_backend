<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\AssessmentUpdated;
use App\Notifications\Assessment\AssessmentUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendAssessmentUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AssessmentUpdated $event): void
    {
        $assessment = $event->assessment;
        
        // Get all students in the class
        $students = $assessment->class->students ?? collect();
        
        if ($students->isEmpty()) {
            return;
        }

        // Send notification to all students
        Notification::send($students, new AssessmentUpdatedNotification($assessment));
    }
}


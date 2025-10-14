<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\AssessmentCreated;
use App\Notifications\Assessment\AssessmentCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendAssessmentCreatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AssessmentCreated $event): void
    {
        $assessment = $event->assessment;
        
        // Get all students in the class
        $students = $assessment->class->students ?? collect();
        
        if ($students->isEmpty()) {
            return;
        }

        // Send notification to all students
        Notification::send($students, new AssessmentCreatedNotification($assessment));
    }
}


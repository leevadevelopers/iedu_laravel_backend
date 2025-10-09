<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\Assessment;
use App\Notifications\Assessment\AssessmentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendAssessmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Assessment $assessment
    ) {}

    public function handle(): void
    {
        // Only send reminders for upcoming assessments
        if ($this->assessment->scheduled_date->isPast()) {
            return;
        }

        // Get all students in the class
        $students = $this->assessment->class->students ?? collect();
        
        if ($students->isEmpty()) {
            return;
        }

        // Send notification to all students
        Notification::send($students, new AssessmentReminderNotification($this->assessment));
    }
}


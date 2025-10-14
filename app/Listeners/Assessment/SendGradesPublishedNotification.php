<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\GradesPublished;
use App\Notifications\Assessment\GradesPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendGradesPublishedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(GradesPublished $event): void
    {
        $assessment = $event->assessment;
        $gradeEntries = $event->gradeEntries;
        
        // Get unique students who received grades
        $studentIds = $gradeEntries->pluck('student_id')->unique();
        $students = \App\Models\User::whereIn('id', $studentIds)->get();
        
        // Send notification to each student
        foreach ($students as $student) {
            $student->notify(new GradesPublishedNotification($assessment, $student));
        }
    }
}


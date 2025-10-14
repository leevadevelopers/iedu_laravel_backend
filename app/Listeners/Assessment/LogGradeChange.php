<?php

namespace App\Listeners\Assessment;

use App\Events\Assessment\GradeEntered;
use App\Models\Assessment\GradesAuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogGradeChange implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(GradeEntered $event): void
    {
        $gradeEntry = $event->gradeEntry;
        
        GradesAuditLog::create([
            'grade_entry_id' => $gradeEntry->id,
            'changed_by' => $gradeEntry->entered_by,
            'action' => 'created',
            'field_name' => 'marks_awarded',
            'old_value' => null,
            'new_value' => $gradeEntry->marks_awarded,
            'reason' => 'Initial grade entry',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}


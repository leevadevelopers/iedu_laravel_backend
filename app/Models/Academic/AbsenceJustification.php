<?php

namespace App\Models\Academic;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceJustification extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'student_id',
        'date',
        'reason',
        'description',
        'attachment_ids',
        'status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'date' => 'date',
        'attachment_ids' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        // Update attendance record if exists
        $this->updateAttendanceRecord();
    }

    public function reject(User $reviewer, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    protected function updateAttendanceRecord(): void
    {
        // Find attendance record for this student and date
        $attendance = \App\Models\V1\Schedule\LessonAttendance::where('student_id', $this->student_id)
            ->whereHas('lesson', function ($query) {
                $query->whereDate('lesson_date', $this->date);
            })
            ->first();

        if ($attendance && $attendance->status === 'absent') {
            $attendance->update([
                'status' => 'excused',
                'notes' => 'Justificado: ' . $this->description,
            ]);
        }
    }
}


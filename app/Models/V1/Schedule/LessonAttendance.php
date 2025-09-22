<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LessonAttendance extends BaseModel
{
    protected $fillable = [
        'school_id', 'lesson_id', 'student_id',
        'status', 'arrival_time', 'departure_time', 'minutes_late', 'minutes_present',
        'marked_by_method', 'notes', 'notified_parent', 'parent_notified_at',
        'check_in_latitude', 'check_in_longitude', 'device_info', 'ip_address',
        'requires_approval', 'approval_status', 'approved_by', 'approved_at', 'approval_notes',
        'marked_by', 'updated_by'
    ];

    protected $casts = [
        'arrival_time' => 'datetime:H:i:s',
        'departure_time' => 'datetime:H:i:s',
        'parent_notified_at' => 'datetime',
        'approved_at' => 'datetime',
        'notified_parent' => 'boolean',
        'requires_approval' => 'boolean',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'minutes_late' => 'integer',
        'minutes_present' => 'integer'
    ];

    protected static function booted()
    {
        static::creating(function ($attendance) {
            // Calculate minutes late if arrival time is set
            if ($attendance->arrival_time && $attendance->lesson) {
                $lessonStart = Carbon::parse($attendance->lesson->start_time);
                $arrival = Carbon::parse($attendance->arrival_time);

                if ($arrival->gt($lessonStart)) {
                    $attendance->minutes_late = $arrival->diffInMinutes($lessonStart);
                    if ($attendance->minutes_late > 15 && $attendance->status === 'present') {
                        $attendance->status = 'late';
                    }
                }
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereIn('status', ['present', 'late', 'online_present']);
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->whereIn('status', ['absent', 'excused']);
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', 'late');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByLesson(Builder $query, int $lessonId): Builder
    {
        return $query->where('lesson_id', $lessonId);
    }

    public function scopeRequiringApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true)
                     ->where('approval_status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    // Methods
    public function isPresent(): bool
    {
        return in_array($this->status, ['present', 'late', 'online_present', 'left_early', 'partial']);
    }

    public function isAbsent(): bool
    {
        return in_array($this->status, ['absent', 'excused']);
    }

    public function isLate(): bool
    {
        return $this->status === 'late' || $this->minutes_late > 0;
    }

    public function isExcused(): bool
    {
        return $this->status === 'excused';
    }

    public function requiresApproval(): bool
    {
        return $this->requires_approval && $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function calculateMinutesPresent(): int
    {
        if (!$this->arrival_time || !$this->departure_time) {
            return 0;
        }

        $arrival = Carbon::parse($this->arrival_time);
        $departure = Carbon::parse($this->departure_time);

        return $departure->diffInMinutes($arrival);
    }

    public function markAsPresent(string $method = 'teacher_manual', array $additionalData = []): bool
    {
        $data = array_merge([
            'status' => 'present',
            'marked_by_method' => $method,
            'arrival_time' => now(),
        ], $additionalData);

        return $this->update($data);
    }

    public function markAsAbsent(string $reason = null): bool
    {
        return $this->update([
            'status' => 'absent',
            'notes' => $reason
        ]);
    }

    public function markAsLate(int $minutesLate, string $method = 'teacher_manual'): bool
    {
        return $this->update([
            'status' => 'late',
            'minutes_late' => $minutesLate,
            'arrival_time' => now(),
            'marked_by_method' => $method
        ]);
    }

    public function excuse(string $reason): bool
    {
        return $this->update([
            'status' => 'excused',
            'notes' => $reason
        ]);
    }

    public function approve(int $userId, string $notes = null): bool
    {
        return $this->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    public function reject(int $userId, string $notes = null): bool
    {
        return $this->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'present' => 'Presente',
            'absent' => 'Ausente',
            'late' => 'Atrasado',
            'excused' => 'Justificado',
            'left_early' => 'Saída antecipada',
            'partial' => 'Presença parcial',
            'online_present' => 'Presente (online)'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getMethodLabelAttribute(): string
    {
        $labels = [
            'teacher_manual' => 'Manual (Professor)',
            'qr_code' => 'QR Code',
            'student_self_checkin' => 'Auto check-in',
            'automatic_online' => 'Automático (online)',
            'biometric' => 'Biometria',
            'rfid' => 'RFID'
        ];
        return $labels[$this->marked_by_method] ?? ucfirst($this->marked_by_method);
    }
}

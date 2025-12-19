<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class BehaviorRecord extends BaseModel
{
    use Tenantable;

    protected $fillable = [
        'tenant_id', 'school_id', 'lesson_session_id', 'student_id',
        'points', 'type', 'category', 'note',
        'recorded_at', 'recorded_by',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'points' => 'integer',
        'recorded_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            // Auto-determine type from points
            if ($record->points > 0) {
                $record->type = 'positive';
            } elseif ($record->points < 0) {
                $record->type = 'negative';
            } else {
                $record->type = null;
            }
        });

        static::updating(function ($record) {
            // Auto-determine type from points
            if ($record->points > 0) {
                $record->type = 'positive';
            } elseif ($record->points < 0) {
                $record->type = 'negative';
            } else {
                $record->type = null;
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lessonSession(): BelongsTo
    {
        return $this->belongsTo(LessonSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePositive(Builder $query): Builder
    {
        return $query->where('type', 'positive');
    }

    public function scopeNegative(Builder $query): Builder
    {
        return $query->where('type', 'negative');
    }

    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByLessonSession(Builder $query, int $lessonSessionId): Builder
    {
        return $query->where('lesson_session_id', $lessonSessionId);
    }

    // Methods
    public function isPositive(): bool
    {
        return $this->type === 'positive' || $this->points > 0;
    }

    public function isNegative(): bool
    {
        return $this->type === 'negative' || $this->points < 0;
    }
}


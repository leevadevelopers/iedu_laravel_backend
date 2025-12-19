<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LessonSession extends BaseModel
{
    use Tenantable;

    protected $fillable = [
        'tenant_id', 'school_id', 'schedule_id',
        'teacher_id', 'subject_id', 'class_id',
        'started_at', 'ended_at', 'duration', 'is_scheduled',
        'status',
        'lesson_note', 'audio_note_url', 'audio_duration', 'lesson_tags',
        'students_present', 'students_absent', 'students_late', 'students_unmarked',
        'attendance_completion_rate',
        'total_behavior_points', 'positive_behavior_count', 'negative_behavior_count',
        'device_id', 'synced_at',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'synced_at' => 'datetime',
        'is_scheduled' => 'boolean',
        'lesson_tags' => 'array',
        'attendance_completion_rate' => 'decimal:2',
        'duration' => 'integer',
        'audio_duration' => 'integer',
        'students_present' => 'integer',
        'students_absent' => 'integer',
        'students_late' => 'integer',
        'students_unmarked' => 'integer',
        'total_behavior_points' => 'integer',
        'positive_behavior_count' => 'integer',
        'negative_behavior_count' => 'integer',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(LessonAttendance::class, 'lesson_session_id');
    }

    public function behaviorRecords(): HasMany
    {
        return $this->hasMany(BehaviorRecord::class);
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
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_scheduled', true);
    }

    public function scopeAdHoc(Builder $query): Builder
    {
        return $query->where('is_scheduled', false);
    }

    // Helper methods
    public function calculateStats(): void
    {
        $attendance = $this->attendanceRecords;
        
        $this->students_present = $attendance->where('status', 'present')->count();
        $this->students_absent = $attendance->where('status', 'absent')->count();
        $this->students_late = $attendance->where('status', 'late')->count();
        // Use filter for Collection instead of orWhere (which is for Query Builder)
        $this->students_unmarked = $attendance->filter(function ($record) {
            return $record->status === null || $record->status === '';
        })->count();
        
        $totalMarked = $this->students_present + $this->students_absent + $this->students_late;
        $totalStudents = $attendance->count();
        
        $this->attendance_completion_rate = $totalStudents > 0 
            ? ($totalMarked / $totalStudents) * 100 
            : 0;
        
        $this->total_behavior_points = $this->behaviorRecords->sum('points');
        $this->positive_behavior_count = $this->behaviorRecords->where('points', '>', 0)->count();
        $this->negative_behavior_count = $this->behaviorRecords->where('points', '<', 0)->count();
        
        $this->save();
    }

    public function complete(): bool
    {
        $this->ended_at = now();
        $this->duration = $this->started_at->diffInSeconds($this->ended_at);
        $this->status = 'completed';
        
        $this->calculateStats();
        
        return $this->save();
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getDurationInMinutes(): int
    {
        if (!$this->duration) {
            return 0;
        }
        return (int) round($this->duration / 60);
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = $this->getDurationInMinutes();
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d', $hours, $mins);
        }
        return sprintf('%d min', $mins);
    }
}


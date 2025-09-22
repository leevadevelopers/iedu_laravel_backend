<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Lesson extends BaseModel
{
    protected $fillable = [
        'school_id', 'schedule_id',
        'title', 'description', 'objectives',
        'subject_id', 'class_id', 'teacher_id', 'academic_term_id',
        'lesson_date', 'start_time', 'end_time', 'duration_minutes',
        'classroom', 'is_online', 'online_meeting_url', 'online_meeting_details',
        'status', 'type',
        'content_summary', 'curriculum_topics', 'homework_assigned', 'homework_due_date',
        'expected_students', 'present_students', 'attendance_rate',
        'teacher_notes', 'lesson_observations', 'student_participation',
        'requires_approval', 'approved_by', 'approved_at',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'homework_due_date' => 'date',
        'objectives' => 'array',
        'online_meeting_details' => 'array',
        'curriculum_topics' => 'array',
        'student_participation' => 'array',
        'is_online' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'attendance_rate' => 'decimal:2',
        'duration_minutes' => 'integer',
        'expected_students' => 'integer',
        'present_students' => 'integer'
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(LessonContent::class)->orderBy('sort_order');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }

    // Scopes
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->where('lesson_date', $date);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('lesson_date', [$startDate, $endDate]);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeBySubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('lesson_date', '>=', now()->toDateString())
                     ->whereIn('status', ['scheduled', 'in_progress'])
                     ->orderBy('lesson_date')
                     ->orderBy('start_time');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('lesson_date', now()->toDateString());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('lesson_date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString()
        ]);
    }

    // Methods
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isOnline(): bool
    {
        return $this->is_online;
    }

    public function isToday(): bool
    {
        return $this->lesson_date->isToday();
    }

    public function isPast(): bool
    {
        return $this->lesson_date->isPast();
    }

    public function isFuture(): bool
    {
        return $this->lesson_date->isFuture();
    }

    public function hasHomework(): bool
    {
        return !empty($this->homework_assigned);
    }

    public function hasContents(): bool
    {
        return $this->contents()->exists();
    }

    public function calculateAttendanceRate(): float
    {
        if ($this->expected_students == 0) return 0;
        return ($this->present_students / $this->expected_students) * 100;
    }

    public function updateAttendanceStats(): void
    {
        $totalStudents = $this->class->current_enrollment;
        $presentCount = $this->attendances()->where('status', 'present')->count();

        $this->update([
            'expected_students' => $totalStudents,
            'present_students' => $presentCount,
            'attendance_rate' => $totalStudents > 0 ? ($presentCount / $totalStudents) * 100 : 0
        ]);
    }

    public function markAsCompleted(array $data = []): bool
    {
        $this->updateAttendanceStats();

        return $this->update(array_merge([
            'status' => 'completed',
        ], $data));
    }

    public function cancel(string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'teacher_notes' => $this->teacher_notes . "\n\nCancelamento: " . ($reason ?? 'Não especificado')
        ]);
    }

    public function getFormattedTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') .
               ' - ' .
               Carbon::parse($this->end_time)->format('H:i');
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'scheduled' => 'Agendada',
            'in_progress' => 'Em andamento',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            'postponed' => 'Adiada',
            'absent_teacher' => 'Professor ausente'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'regular' => 'Regular',
            'makeup' => 'Reposição',
            'extra' => 'Extra',
            'review' => 'Revisão',
            'exam' => 'Avaliação',
            'practical' => 'Prática',
            'field_trip' => 'Excursão'
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }
}

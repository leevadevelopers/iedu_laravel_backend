<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Schedule extends BaseModel
{
    protected $fillable = [
        'school_id', 'academic_year_id', 'academic_term_id',
        'name', 'description',
        'subject_id', 'class_id', 'teacher_id', 'classroom',
        'period', 'day_of_week', 'start_time', 'end_time',
        'start_date', 'end_date', 'recurrence_pattern',
        'status', 'is_online', 'online_meeting_url', 'configuration_json',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'start_date' => 'date',
        'end_date' => 'date',
        'recurrence_pattern' => 'array',
        'configuration_json' => 'array',
        'is_online' => 'boolean'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeByDay(Builder $query, string $day): Builder
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTimeRange(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<=', $startTime)
                     ->where('end_time', '>=', $endTime);
              });
        });
    }

    public function scopeConflictsWith(Builder $query, int $teacherId, string $dayOfWeek, string $startTime, string $endTime): Builder
    {
        return $query->where('teacher_id', $teacherId)
                     ->where('day_of_week', $dayOfWeek)
                     ->where('status', 'active')
                     ->where(function ($q) use ($startTime, $endTime) {
                         $q->whereBetween('start_time', [$startTime, $endTime])
                           ->orWhereBetween('end_time', [$startTime, $endTime])
                           ->orWhere(function ($q2) use ($startTime, $endTime) {
                               $q2->where('start_time', '<=', $startTime)
                                  ->where('end_time', '>=', $endTime);
                           });
                     });
    }

    // Accessors & Methods
    public function getDurationInMinutesAttribute(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $end->diffInMinutes($start);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnline(): bool
    {
        return $this->is_online;
    }

    public function hasConflictWith(Schedule $otherSchedule): bool
    {
        if ($this->teacher_id !== $otherSchedule->teacher_id ||
            $this->day_of_week !== $otherSchedule->day_of_week) {
            return false;
        }

        $thisStart = Carbon::parse($this->start_time);
        $thisEnd = Carbon::parse($this->end_time);
        $otherStart = Carbon::parse($otherSchedule->start_time);
        $otherEnd = Carbon::parse($otherSchedule->end_time);

        return $thisStart->lt($otherEnd) && $thisEnd->gt($otherStart);
    }

    public function generateLessons(): array
    {
        $lessons = [];
        $currentDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        while ($currentDate->lte($endDate)) {
            if ($currentDate->dayOfWeek === $this->getDayOfWeekNumber()) {
                $lessons[] = [
                    'schedule_id' => $this->id,
                    'school_id' => $this->school_id,
                    'subject_id' => $this->subject_id,
                    'class_id' => $this->class_id,
                    'teacher_id' => $this->teacher_id,
                    'academic_term_id' => $this->academic_term_id,
                    'lesson_date' => $currentDate->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'duration_minutes' => $this->duration_in_minutes,
                    'classroom' => $this->classroom,
                    'is_online' => $this->is_online,
                    'online_meeting_url' => $this->online_meeting_url,
                    'title' => $this->name,
                    'status' => 'scheduled',
                    'type' => 'regular',
                    'created_by' => $this->created_by,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentDate->addDay();
        }

        return $lessons;
    }

    private function getDayOfWeekNumber(): int
    {
        $days = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];
        return $days[$this->day_of_week] ?? 1;
    }

    public function getFormattedTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') .
               ' - ' .
               Carbon::parse($this->end_time)->format('H:i');
    }

    public function getPeriodLabelAttribute(): string
    {
        $labels = [
            'morning' => 'Manhã',
            'afternoon' => 'Tarde',
            'evening' => 'Noite',
            'night' => 'Madrugada'
        ];
        return $labels[$this->period] ?? ucfirst($this->period);
    }

    public function getDayOfWeekLabelAttribute(): string
    {
        $labels = [
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo'
        ];
        return $labels[$this->day_of_week] ?? ucfirst($this->day_of_week);
    }
}

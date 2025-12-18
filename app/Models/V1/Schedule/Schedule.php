<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
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
    use Tenantable;

    protected $fillable = [
        'tenant_id', 'school_id', 'academic_year_id', 'academic_term_id',
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
        // Handle both Carbon instances and time strings
        if ($this->start_time instanceof Carbon && $this->end_time instanceof Carbon) {
            $start = $this->start_time->copy()->setDate(2000, 1, 1);
            $end = $this->end_time->copy()->setDate(2000, 1, 1);
        } else {
            $startTimeStr = $this->start_time instanceof Carbon 
                ? $this->start_time->format('H:i:s') 
                : (string) $this->start_time;
            $endTimeStr = $this->end_time instanceof Carbon 
                ? $this->end_time->format('H:i:s') 
                : (string) $this->end_time;
            
            $start = Carbon::createFromFormat('H:i:s', $startTimeStr);
            $end = Carbon::createFromFormat('H:i:s', $endTimeStr);
        }
        
        // If end is before start, add a day (shouldn't happen for same-day lessons)
        if ($end->lt($start)) {
            $end->addDay();
        }
        
        return $start->diffInMinutes($end);
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
        $currentDate = Carbon::parse($this->start_date)->startOfDay();
        $endDate = Carbon::parse($this->end_date)->endOfDay();
        $targetDayOfWeek = $this->getDayOfWeekNumber();

        // Prefer schedule's academic_term_id, but gracefully fall back to class academic_term_id
        $academicTermId = $this->academic_term_id;
        if (!$academicTermId && $this->relationLoaded('class') || $this->class) {
            $academicTermId = $this->class->academic_term_id ?? $academicTermId;
        }

        // Calculate duration correctly - parse times with same date context
        if ($this->start_time instanceof Carbon && $this->end_time instanceof Carbon) {
            $startTime = $this->start_time->copy()->setDate(2000, 1, 1);
            $endTime = $this->end_time->copy()->setDate(2000, 1, 1);
        } else {
            $startTimeStr = $this->start_time instanceof Carbon 
                ? $this->start_time->format('H:i:s') 
                : (string) $this->start_time;
            $endTimeStr = $this->end_time instanceof Carbon 
                ? $this->end_time->format('H:i:s') 
                : (string) $this->end_time;
            
            $startTime = Carbon::createFromFormat('H:i:s', $startTimeStr);
            $endTime = Carbon::createFromFormat('H:i:s', $endTimeStr);
        }
        
        // If end time is before start time, it means it's next day (shouldn't happen for same-day lessons)
        if ($endTime->lt($startTime)) {
            $endTime->addDay();
        }
        $durationMinutes = $startTime->diffInMinutes($endTime);

        while ($currentDate->lte($endDate)) {
            // Carbon's dayOfWeek: 0=Sunday, 1=Monday, 2=Tuesday, etc.
            if ($currentDate->dayOfWeek === $targetDayOfWeek) {
                $lessons[] = [
                    'schedule_id' => $this->id,
                    'tenant_id' => $this->tenant_id,
                    'school_id' => $this->school_id,
                    'subject_id' => $this->subject_id,
                    'class_id' => $this->class_id,
                    'teacher_id' => $this->teacher_id,
                    'academic_term_id' => $academicTermId,
                    'lesson_date' => $currentDate->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'duration_minutes' => $durationMinutes,
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

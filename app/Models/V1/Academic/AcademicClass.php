<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Models\V1\SIS\School\AcademicTerm;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArray;

class AcademicClass extends BaseModel
{
    use Tenantable;
    protected $table = 'classes';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'subject_id',
        'academic_year_id',
        'academic_term_id',
        'name',
        'section',
        'class_code',
        'grade_level',
        'max_students',
        'current_enrollment',
        'primary_teacher_id',
        'additional_teachers_json',
        'schedule_json',
        'room_number',
        'status'
    ];

    protected $casts = [
        'additional_teachers_json' => 'array',
        'schedule_json' => 'array',
        'max_students' => 'integer',
        'current_enrollment' => 'integer'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function primaryTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'primary_teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_class_enrollments', 'class_id', 'student_id')
                    ->withPivot('enrollment_date', 'status', 'final_grade')
                    ->withTimestamps();
    }

    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'class_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByGrade(Builder $query, string $gradeLevel): Builder
    {
        return $query->where('grade_level', $gradeLevel);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('primary_teacher_id', $teacherId);
    }

    public function scopeBySubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByAcademicYear(Builder $query, int $yearId): Builder
    {
        return $query->where('academic_year_id', $yearId);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasAvailableSeats(): bool
    {
        return $this->current_enrollment < $this->max_students;
    }

    public function getAvailableSeats(): int
    {
        return max(0, $this->max_students - $this->current_enrollment);
    }

    public function getEnrollmentPercentage(): float
    {
        if ($this->max_students == 0) return 0;
        return ($this->current_enrollment / $this->max_students) * 100;
    }

    public function addStudent($student): bool
    {
        if (!$this->hasAvailableSeats()) {
            return false;
        }

        $this->students()->attach($student->id, [
            'enrollment_date' => now(),
            'status' => 'active'
        ]);

        $this->increment('current_enrollment');
        return true;
    }

    public function removeStudent($student): bool
    {
        $this->students()->detach($student->id);
        $this->decrement('current_enrollment');
        return true;
    }
}

<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GradeEntry extends BaseModel
{
    use Tenantable;
    protected $fillable = [
        'tenant_id',
        'school_id',
        'student_id',
        'class_id',
        'academic_term_id',
        'assessment_name',
        'assessment_type',
        'assessment_date',
        'raw_score',
        'percentage_score',
        'letter_grade',
        'points_earned',
        'points_possible',
        'grade_category',
        'weight',
        'entered_by',
        'entered_at',
        'modified_by',
        'modified_at',
        'teacher_comments',
        'private_notes'
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'raw_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'points_earned' => 'decimal:2',
        'points_possible' => 'decimal:2',
        'weight' => 'decimal:2',
        'entered_at' => 'datetime',
        'modified_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::creating(function ($gradeEntry) {
            $gradeEntry->entered_at = now();
        });

        static::updating(function ($gradeEntry) {
            $gradeEntry->modified_at = now();
        });
    }

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'entered_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'modified_by');
    }

    // Scopes
    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTerm(Builder $query, int $termId): Builder
    {
        return $query->where('academic_term_id', $termId);
    }

    public function scopeByAssessmentType(Builder $query, string $type): Builder
    {
        return $query->where('assessment_type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('grade_category', $category);
    }

    // Methods
    public function calculatePercentage(): float
    {
        if ($this->points_possible > 0) {
            return ($this->points_earned / $this->points_possible) * 100;
        }
        return $this->percentage_score ?? 0;
    }

    public function getWeightedScore(): float
    {
        return $this->calculatePercentage() * ($this->weight ?? 1.0);
    }

    public function isPassing(): bool
    {
        return $this->percentage_score >= 60; // Configurable threshold
    }

    public function hasComments(): bool
    {
        return !empty($this->teacher_comments);
    }

    public function wasModified(): bool
    {
        return !is_null($this->modified_at);
    }
}

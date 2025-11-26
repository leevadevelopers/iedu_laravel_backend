<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'term_id',
        'subject_id',
        'class_id',
        'teacher_id',
        'type_id',
        'title',
        'description',
        'instructions',
        'scheduled_date',
        'start_time',
        'duration_minutes',
        'submission_deadline',
        'total_marks',
        'weight',
        'visibility',
        'allow_upload_submissions',
        'status',
        'is_locked',
        'published_at',
        'published_by',
        'metadata',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'start_time' => 'datetime:H:i:s',
        'submission_deadline' => 'datetime',
        'published_at' => 'datetime',
        'total_marks' => 'decimal:2',
        'weight' => 'decimal:2',
        'allow_upload_submissions' => 'boolean',
        'is_locked' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the assessment term.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AssessmentTerm::class, 'term_id');
    }

    /**
     * Get the subject.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Get the class.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    /**
     * Get the teacher.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the assessment type.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class, 'type_id');
    }

    /**
     * Get the user who published this assessment.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Get the components for this assessment.
     */
    public function components(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class, 'assessment_id')->orderBy('order');
    }

    /**
     * Get the grade entries for this assessment.
     * Uses the existing grade_entries table with assessment_name filter
     */
    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'assessment_name', 'title')
                    ->where('class_id', $this->class_id)
                    ->where('academic_term_id', $this->term_id);
    }

    /**
     * Get the resources for this assessment.
     */
    public function resources(): HasMany
    {
        return $this->hasMany(AssessmentResource::class, 'assessment_id')->orderBy('order');
    }

    /**
     * Scope to get only published assessments.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope to get assessments by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get assessments for a specific teacher.
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope to get assessments for a specific class.
     */
    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Check if the assessment is locked.
     */
    public function isLocked(): bool
    {
        return $this->is_locked;
    }

    /**
     * Check if the assessment is published.
     */
    public function isPublished(): bool
    {
        return !is_null($this->published_at);
    }
}


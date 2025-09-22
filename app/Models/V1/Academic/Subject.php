<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Subject extends BaseModel
{
    use Tenantable;
    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'code',
        'description',
        'subject_area',
        'grade_levels',
        'learning_standards_json',
        'prerequisites',
        'credit_hours',
        'is_core_subject',
        'is_elective',
        'status'
    ];

    protected $casts = [
        'grade_levels' => 'array',
        'learning_standards_json' => 'array',
        'prerequisites' => 'array',
        'credit_hours' => 'decimal:1',
        'is_core_subject' => 'boolean',
        'is_elective' => 'boolean'
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

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('is_core_subject', true);
    }

    public function scopeElective(Builder $query): Builder
    {
        return $query->where('is_elective', true);
    }

    public function scopeByArea(Builder $query, string $area): Builder
    {
        return $query->where('subject_area', $area);
    }

    public function scopeByGradeLevel(Builder $query, string $gradeLevel): Builder
    {
        return $query->whereJsonContains('grade_levels', $gradeLevel);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCoreSubject(): bool
    {
        return $this->is_core_subject;
    }

    public function isElective(): bool
    {
        return $this->is_elective;
    }

    public function supportsGradeLevel(string $gradeLevel): bool
    {
        return in_array($gradeLevel, $this->grade_levels ?? []);
    }

    public function hasPrerequisites(): bool
    {
        return !empty($this->prerequisites);
    }
}

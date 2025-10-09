<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradingSystem extends BaseModel
{
    use Tenantable;

    protected $fillable = [
        'school_id',
        'tenant_id',
        'name',
        'system_type',
        'applicable_grades',
        'applicable_subjects',
        'is_primary',
        'configuration_json',
        'status',
    ];

    protected $casts = [
        'applicable_grades' => 'array',
        'applicable_subjects' => 'array',
        'is_primary' => 'boolean',
        'configuration_json' => 'array',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function gradeScales(): HasMany
    {
        return $this->hasMany(GradeScale::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('system_type', $type);
    }

    // Methods
    
    /**
     * Get the default scale for this system
     */
    public function getDefaultScale(): ?GradeScale
    {
        return $this->gradeScales()->where('is_default', true)->first();
    }

    /**
     * Check if this system is applicable to a specific grade level
     */
    public function isApplicableToGrade(string $gradeLevel): bool
    {
        if (empty($this->applicable_grades)) {
            return true; // Applicable to all if not specified
        }

        return in_array($gradeLevel, $this->applicable_grades);
    }

    /**
     * Check if this system is applicable to a specific subject
     */
    public function isApplicableToSubject(int $subjectId): bool
    {
        if (empty($this->applicable_subjects)) {
            return true; // Applicable to all if not specified
        }

        return in_array($subjectId, $this->applicable_subjects);
    }
}

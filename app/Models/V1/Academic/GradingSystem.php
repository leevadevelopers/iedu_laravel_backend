<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradingSystem extends BaseModel
{
    use Tenantable;
    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'system_type',
        'applicable_grades',
        'applicable_subjects',
        'is_primary',
        'configuration_json',
        'status'
    ];

    protected $casts = [
        'applicable_grades' => 'array',
        'applicable_subjects' => 'array',
        'configuration_json' => 'array',
        'is_primary' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($gradingSystem) {
            if ($gradingSystem->is_primary) {
                static::where('school_id', $gradingSystem->school_id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($gradingSystem) {
            if ($gradingSystem->is_primary && $gradingSystem->isDirty('is_primary')) {
                static::where('school_id', $gradingSystem->school_id)
                    ->where('id', '!=', $gradingSystem->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
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
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function supportsGrade(string $grade): bool
    {
        return in_array($grade, $this->applicable_grades ?? []);
    }

    public function supportsSubject(string $subject): bool
    {
        return in_array($subject, $this->applicable_subjects ?? []);
    }
}

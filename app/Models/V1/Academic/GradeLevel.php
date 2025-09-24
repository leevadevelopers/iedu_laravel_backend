<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradeLevel extends BaseModel
{
    use Tenantable;
    protected $fillable = [
        'tenant_id',
        'grade_scale_id',
        'school_id',
        'grade_value',
        'display_value',
        'numeric_value',
        'gpa_points',
        'percentage_min',
        'percentage_max',
        'description',
        'color_code',
        'is_passing',
        'sort_order'
    ];

    protected $casts = [
        'numeric_value' => 'decimal:2',
        'gpa_points' => 'decimal:2',
        'percentage_min' => 'decimal:2',
        'percentage_max' => 'decimal:2',
        'is_passing' => 'boolean',
        'sort_order' => 'integer'
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

    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'letter_grade', 'grade_value');
    }

    // Scopes
    public function scopePassing(Builder $query): Builder
    {
        return $query->where('is_passing', true);
    }

    public function scopeFailing(Builder $query): Builder
    {
        return $query->where('is_passing', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // Methods
    public function isPassing(): bool
    {
        return $this->is_passing;
    }

    public function isFailing(): bool
    {
        return !$this->is_passing;
    }

    public function isInRange(float $percentage): bool
    {
        return $percentage >= $this->percentage_min && $percentage <= $this->percentage_max;
    }
}

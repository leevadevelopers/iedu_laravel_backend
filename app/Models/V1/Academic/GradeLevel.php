<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GradeLevel extends BaseModel
{
    protected $fillable = [
        'grade_scale_id',
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
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
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

<?php

namespace App\Models\V1\Academic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeScaleRange extends Model
{
    protected $fillable = [
        'grade_scale_id',
        'min_value',
        'max_value',
        'display_label',
        'description',
        'color',
        'gpa_equivalent',
        'is_passing',
        'order',
    ];

    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'gpa_equivalent' => 'decimal:2',
        'is_passing' => 'boolean',
        'order' => 'integer',
    ];

    // Relationships
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    // Methods
    
    /**
     * Check if a score falls within this range
     */
    public function containsScore(float $score): bool
    {
        return $score >= $this->min_value && $score <= $this->max_value;
    }

    /**
     * Get the midpoint of this range
     */
    public function getMidpoint(): float
    {
        return ($this->min_value + $this->max_value) / 2;
    }

    /**
     * Get the range width
     */
    public function getWidth(): float
    {
        return $this->max_value - $this->min_value;
    }
}


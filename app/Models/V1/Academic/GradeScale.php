<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradeScale extends BaseModel
{
    use Tenantable;

    protected $fillable = [
        'school_id',
        'tenant_id',
        'name',
        'code',
        'description',
        'scale_type',
        'min_value',
        'max_value',
        'passing_grade',
        'status',
        'is_default',
        'configuration_json',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'passing_grade' => 'decimal:2',
        'configuration_json' => 'array',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(GradeScaleRange::class)->orderBy('order');
    }

    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class)->orderBy('sort_order');
    }

    // Scopes
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('scale_type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Methods
    
    /**
     * Convert a numeric score to the appropriate grade label
     */
    public function convertScoreToGrade(float $score): ?array
    {
        $range = $this->ranges()
            ->where('min_value', '<=', $score)
            ->where('max_value', '>=', $score)
            ->first();

        if (!$range) {
            return null;
        }

        return [
            'label' => $range->display_label,
            'description' => $range->description,
            'color' => $range->color,
            'gpa_equivalent' => $range->gpa_equivalent,
            'is_passing' => $range->is_passing,
        ];
    }

    /**
     * Get the label for a score
     */
    public function getGradeLabel(float $score): ?string
    {
        $grade = $this->convertScoreToGrade($score);
        return $grade['label'] ?? null;
    }

    /**
     * Check if a score is passing
     */
    public function isPassing(float $score): bool
    {
        $range = $this->ranges()
            ->where('min_value', '<=', $score)
            ->where('max_value', '>=', $score)
            ->first();

        return $range ? $range->is_passing : false;
    }

    /**
     * Get GPA equivalent for a score
     */
    public function getGPAEquivalent(float $score): ?float
    {
        $grade = $this->convertScoreToGrade($score);
        return $grade['gpa_equivalent'] ?? null;
    }

    /**
     * Convert percentage to this scale
     */
    public function convertFromPercentage(float $percentage): ?string
    {
        // If this is a percentage scale, return as is
        if ($this->scale_type === 'percentage') {
            return number_format($percentage, 2) . '%';
        }

        // If this is a points scale (0-20, 0-10, etc.)
        if ($this->scale_type === 'points') {
            $maxPoints = $this->ranges()->max('max_value');
            $points = ($percentage / 100) * $maxPoints;
            return $this->getGradeLabel($points);
        }

        // For letter scales, convert percentage to letter
        if ($this->scale_type === 'letter') {
            return $this->getGradeLabel($percentage);
        }

        return null;
    }

    /**
     * Get all passing grades
     */
    public function getPassingGrades(): array
    {
        return $this->ranges()
            ->where('is_passing', true)
            ->orderBy('order')
            ->pluck('display_label')
            ->toArray();
    }

    /**
     * Get minimum passing score
     */
    public function getMinimumPassingScore(): ?float
    {
        $passingRange = $this->ranges()
            ->where('is_passing', true)
            ->orderBy('min_value')
            ->first();

        return $passingRange?->min_value;
    }
}

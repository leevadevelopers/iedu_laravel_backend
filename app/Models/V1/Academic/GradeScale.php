<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradeScale extends BaseModel
{
    protected $fillable = [
        'grading_system_id',
        'school_id',
        'name',
        'scale_type',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($gradeScale) {
            if ($gradeScale->is_default) {
                static::where('grading_system_id', $gradeScale->grading_system_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
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

    // Methods
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function getGradeForPercentage(float $percentage): ?GradeLevel
    {
        return $this->gradeLevels()
            ->where('percentage_min', '<=', $percentage)
            ->where('percentage_max', '>=', $percentage)
            ->first();
    }
}

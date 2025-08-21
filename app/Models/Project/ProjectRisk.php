<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Enums\Project\RiskStatus;
use App\Enums\Project\RiskCategory;

class ProjectRisk extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'title',
        'description',
        'category',
        'probability',
        'impact',
        'risk_score',
        'status',
        'mitigation_strategy',
        'contingency_plan',
        'owner_id',
        'review_date',
        'identified_date',
    ];

    protected $casts = [
        'probability' => 'integer',
        'impact' => 'integer',
        'risk_score' => 'decimal:2',
        'review_date' => 'date',
        'identified_date' => 'date',
        'status' => RiskStatus::class,
        'category' => RiskCategory::class,
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    // Scopes
    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 15);
    }

    public function scopeActive($query)
    {
        return $query->where('status', RiskStatus::ACTIVE);
    }

    // Accessors
    public function getRiskLevelAttribute(): string
    {
        return match(true) {
            $this->risk_score >= 20 => 'critical',
            $this->risk_score >= 15 => 'high',
            $this->risk_score >= 10 => 'medium',
            $this->risk_score >= 5 => 'low',
            default => 'minimal'
        };
    }

    // Mutators
    public function setRiskScoreAttribute($value): void
    {
        // Auto-calculate risk score if not provided
        if (is_null($value) && $this->probability && $this->impact) {
            $this->attributes['risk_score'] = $this->probability * $this->impact;
        } else {
            $this->attributes['risk_score'] = $value;
        }
    }
}

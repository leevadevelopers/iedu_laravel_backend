<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasFormInstance;
use App\Models\Traits\Tenantable;
use App\Models\Traits\HasWorkflow;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;

class Project extends Model
{
    use SoftDeletes, Tenantable, HasFormInstance, HasWorkflow;

    protected $fillable = [
        'tenant_id',
        'form_instance_id',
        'name',
        'code',
        'description',
        'category',
        'priority',
        'status',
        'start_date',
        'end_date',
        'budget',
        'currency',
        'methodology_type',
        'metadata',
        'compliance_requirements',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'metadata' => 'array',
        'compliance_requirements' => 'array',
        'status' => ProjectStatus::class,
        'priority' => ProjectPriority::class,
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function formInstance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\FormInstance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function team(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'project_users')
                    ->withPivot('role', 'access_level', 'joined_at')
                    ->withTimestamps();
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(ProjectStakeholder::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(\App\Models\Budget::class);
    }

    public function indicators(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\Indicator::class,
            \App\Models\IndicatorFramework::class
        );
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ProjectStatus::ACTIVE);
    }

    public function scopeByMethodology($query, string $methodology)
    {
        return $query->where('methodology_type', $methodology);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority($query, ProjectPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverBudget($query, float $amount)
    {
        return $query->where('budget', '>', $amount);
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereBetween('start_date', [now(), now()->addDays($days)]);
    }

    // Accessors & Mutators
    public function getDurationInDaysAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        return $this->start_date->diffInDays($this->end_date);
    }

    public function getProgressPercentageAttribute(): float
    {
        // Basic time-based calculation - can be enhanced with milestone data
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $totalDays = $this->start_date->diffInDays($this->end_date);
        $elapsedDays = $this->start_date->diffInDays(now());

        if ($totalDays <= 0) {
            return 0;
        }

        return min(100, max(0, ($elapsedDays / $totalDays) * 100));
    }

    public function getBudgetUtilizationAttribute(): float
    {
        // This would integrate with financial transactions
        // Placeholder calculation
        return 30.0; // 30% utilized
    }

    public function getHealthScoreAttribute(): array
    {
        // Comprehensive health calculation
        $factors = [
            'timeline' => $this->getTimelineHealth(),
            'budget' => $this->getBudgetHealth(),
            'milestones' => $this->getMilestoneHealth(),
            'risks' => $this->getRiskHealth(),
        ];

        $overallScore = array_sum($factors) / count($factors);

        return [
            'overall' => round($overallScore, 2),
            'factors' => $factors,
            'status' => $this->getHealthStatus($overallScore)
        ];
    }

    public function getRiskScoreAttribute(): array
    {
        $riskFactors = [
            'timeline_risk' => $this->getTimelineRisk(),
            'budget_risk' => $this->getBudgetRisk(),
            'complexity_risk' => $this->getComplexityRisk(),
        ];

        $overallRisk = max($riskFactors);

        return [
            'overall' => round($overallRisk, 2),
            'factors' => $riskFactors,
            'level' => $this->getRiskLevel($overallRisk)
        ];
    }

    // Helper methods for health calculations
    private function getTimelineHealth(): float
    {
        $progress = $this->progress_percentage;
        $timeProgress = $this->progress_percentage; // Time-based progress
        
        $variance = abs($progress - $timeProgress);
        return max(0, 100 - $variance);
    }

    private function getBudgetHealth(): float
    {
        $utilization = $this->budget_utilization;
        $timeProgress = $this->progress_percentage;
        
        $variance = abs($utilization - $timeProgress);
        return max(0, 100 - $variance * 2);
    }

    private function getMilestoneHealth(): float
    {
        $milestones = $this->milestones;
        if ($milestones->isEmpty()) return 100;
        
        $onTrack = $milestones->where('status', 'on_track')->count();
        $total = $milestones->count();
        
        return ($onTrack / $total) * 100;
    }

    private function getRiskHealth(): float
    {
        $riskScore = $this->risk_score['overall'];
        return 100 - ($riskScore * 100);
    }

    private function getTimelineRisk(): float
    {
        $remainingDays = now()->diffInDays($this->end_date, false);
        $progress = $this->progress_percentage;
        
        if ($remainingDays < 0) return 1.0; // Overdue
        if ($progress < 50 && $remainingDays < 30) return 0.8; // High risk
        if ($progress < 75 && $remainingDays < 60) return 0.5; // Medium risk
        
        return 0.2; // Low risk
    }

    private function getBudgetRisk(): float
    {
        $utilization = $this->budget_utilization;
        $timeProgress = $this->progress_percentage;
        
        if ($utilization > $timeProgress + 20) return 0.9; // Very high risk
        if ($utilization > $timeProgress + 10) return 0.6; // High risk
        if ($utilization > $timeProgress + 5) return 0.3; // Medium risk
        
        return 0.1; // Low risk
    }

    private function getComplexityRisk(): float
    {
        $risk = 0;
        
        // Budget complexity
        if ($this->budget > 1000000) $risk += 0.2;
        if ($this->budget > 5000000) $risk += 0.2;
        
        // Duration complexity
        if ($this->duration_in_days > 365) $risk += 0.2;
        if ($this->duration_in_days > 730) $risk += 0.2;
        
        // Team size complexity (if available)
        // $teamSize = $this->team->count();
        // if ($teamSize > 20) $risk += 0.2;
        
        return min(1.0, $risk);
    }

    private function getHealthStatus(float $score): string
    {
        return match(true) {
            $score >= 85 => 'excellent',
            $score >= 70 => 'good',
            $score >= 50 => 'warning',
            $score >= 30 => 'poor',
            default => 'critical'
        };
    }

    private function getRiskLevel(float $risk): string
    {
        return match(true) {
            $risk >= 0.8 => 'critical',
            $risk >= 0.6 => 'high',
            $risk >= 0.4 => 'medium',
            $risk >= 0.2 => 'low',
            default => 'minimal'
        };
    }

    // Methodology-specific methods
    public function getMethodologyRequirements(): array
    {
        return match($this->methodology_type) {
            'usaid' => $this->getUSAIDRequirements(),
            'world_bank' => $this->getWorldBankRequirements(),
            'eu' => $this->getEURequirements(),
            default => []
        };
    }

    private function getUSAIDRequirements(): array
    {
        return [
            'environmental_screening' => 'required',
            'gender_integration' => 'required',
            'marking_branding' => 'required',
            'procurement_plan' => 'required',
            'results_framework' => 'required'
        ];
    }

    private function getWorldBankRequirements(): array
    {
        return [
            'safeguards_screening' => 'required',
            'results_framework' => 'required',
            'procurement_plan' => 'required',
            'financial_management' => 'required',
            'monitoring_evaluation' => 'required'
        ];
    }

    private function getEURequirements(): array
    {
        return [
            'logical_framework' => 'required',
            'sustainability_plan' => 'required',
            'visibility_plan' => 'required',
            'risk_management' => 'required',
            'quality_assurance' => 'required'
        ];
    }

    // Workflow state methods
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = $this->getAllowedTransitions();
        return in_array($newStatus, $allowedTransitions[$this->status->value] ?? []);
    }

    private function getAllowedTransitions(): array
    {
        return [
            'draft' => ['pending_approval', 'cancelled'],
            'pending_approval' => ['approved', 'rejected', 'draft'],
            'approved' => ['active', 'cancelled'],
            'active' => ['on_hold', 'completed', 'cancelled'],
            'on_hold' => ['active', 'cancelled'],
            'completed' => [],
            'cancelled' => ['draft'],
            'rejected' => ['draft', 'cancelled']
        ];
    }
}

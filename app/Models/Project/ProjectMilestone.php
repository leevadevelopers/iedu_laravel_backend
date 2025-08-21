<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use App\Enums\Project\MilestoneStatus;

class ProjectMilestone extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'name',
        'description',
        'target_date',
        'completion_date',
        'status',
        'weight',
        'deliverables',
        'success_criteria',
        'responsible_user_id',
        'dependencies',
        'notes',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completion_date' => 'date',
        'weight' => 'decimal:2',
        'deliverables' => 'array',
        'success_criteria' => 'array',
        'dependencies' => 'array',
        'status' => MilestoneStatus::class,
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    // Scopes
    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereBetween('target_date', [now(), now()->addDays($days)]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('target_date', '<', now())
                    ->where('status', '!=', MilestoneStatus::COMPLETED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', MilestoneStatus::COMPLETED);
    }

    // Accessors
    public function getDaysUntilDueAttribute(): int
    {
        return now()->diffInDays($this->target_date, false);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->target_date < now() && $this->status !== MilestoneStatus::COMPLETED;
    }

    public function getProgressStatusAttribute(): string
    {
        if ($this->status === MilestoneStatus::COMPLETED) {
            return 'completed';
        }
        
        if ($this->is_overdue) {
            return 'overdue';
        }
        
        if ($this->days_until_due <= 7) {
            return 'due_soon';
        }
        
        if ($this->days_until_due <= 30) {
            return 'upcoming';
        }
        
        return 'on_track';
    }
}

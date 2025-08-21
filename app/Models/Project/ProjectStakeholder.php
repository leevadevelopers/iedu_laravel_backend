<?php

namespace App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class ProjectStakeholder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'tenant_id',
        'name',
        'email',
        'organization',
        'role',
        'influence_level',
        'interest_level',
        'communication_preference',
        'notes',
    ];

    protected $casts = [
        'influence_level' => 'integer',
        'interest_level' => 'integer',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    // Accessors
    public function getStakeholderMatrixAttribute(): string
    {
        // Stakeholder analysis matrix based on influence and interest
        return match(true) {
            $this->influence_level >= 4 && $this->interest_level >= 4 => 'manage_closely',
            $this->influence_level >= 4 && $this->interest_level < 4 => 'keep_satisfied',
            $this->influence_level < 4 && $this->interest_level >= 4 => 'keep_informed',
            default => 'monitor'
        };
    }
}

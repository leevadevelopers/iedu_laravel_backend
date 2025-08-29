<?php

namespace App\Models;

use App\Traits\HasOrganization;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormWorkflow extends BaseModel
{
    use HasFactory, HasOrganization, Tenantable;

    protected $fillable = [
        'tenant_id',
        'form_instance_id',
        'workflow_type',
        'current_step',
        'total_steps',
        'steps_configuration_json',
        'current_step_data_json',
        'status',
        'started_at',
        'completed_at',
        'escalated_at',
        'escalation_level',
        'escalation_reason'
    ];

    protected $casts = [
        'current_step' => 'integer',
        'total_steps' => 'integer',
        'steps_configuration_json' => 'array',
        'current_step_data_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalation_level' => 'integer'
    ];

    /**
     * Get the form instance.
     */
    public function formInstance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Forms\FormInstance::class);
    }

    /**
     * Get the workflow steps.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(FormWorkflowStep::class, 'workflow_id');
    }
}

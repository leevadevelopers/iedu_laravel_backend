<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormWorkflowStep extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'step_number',
        'step_name',
        'step_type',
        'required_role',
        'assigned_user_id',
        'instructions',
        'required_actions_json',
        'form_modifications_allowed',
        'status',
        'started_at',
        'completed_at',
        'decision',
        'comments',
        'decision_by',
        'decision_date',
        'attachments_json',
        'evidence_json'
    ];

    protected $casts = [
        'step_number' => 'integer',
        'required_actions_json' => 'array',
        'form_modifications_allowed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'decision_date' => 'datetime',
        'attachments_json' => 'array',
        'evidence_json' => 'array'
    ];

    /**
     * Get the workflow.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(FormWorkflow::class);
    }

    /**
     * Get the assigned user.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the user who made the decision.
     */
    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }
}

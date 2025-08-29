<?php

namespace App\Traits;

use App\Models\FormWorkflow;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasWorkflow
{
    /**
     * Get the current workflow for this model.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(FormWorkflow::class, 'approval_workflow_id');
    }

    /**
     * Get all workflows for this model.
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(FormWorkflow::class, 'form_instance_id')
            ->where('form_instance_type', static::class);
    }

    /**
     * Start a workflow for this model.
     */
    public function startWorkflow(string $workflowType, array $config = []): FormWorkflow
    {
        $workflow = FormWorkflow::create([
            'form_instance_id' => $this->id,
            'form_instance_type' => static::class,
            'workflow_type' => $workflowType,
            'current_step' => 1,
            'total_steps' => count($config['steps'] ?? []),
            'steps_configuration_json' => $config,
            'status' => 'pending'
        ]);

        $this->update(['approval_workflow_id' => $workflow->id]);

        return $workflow;
    }

    /**
     * Complete the current workflow.
     */
    public function completeWorkflow(): bool
    {
        if ($this->workflow) {
            $this->workflow->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            return true;
        }

        return false;
    }

    /**
     * Cancel the current workflow.
     */
    public function cancelWorkflow(string $reason = null): bool
    {
        if ($this->workflow) {
            $this->workflow->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'escalation_reason' => $reason
            ]);

            $this->update(['approval_workflow_id' => null]);

            return true;
        }

        return false;
    }

    /**
     * Check if the model has an active workflow.
     */
    public function hasActiveWorkflow(): bool
    {
        return $this->workflow && in_array($this->workflow->status, ['pending', 'in_progress']);
    }

    /**
     * Get the current workflow step.
     */
    public function getCurrentWorkflowStep(): ?array
    {
        if (!$this->workflow) {
            return null;
        }

        $steps = $this->workflow->steps_configuration_json['steps'] ?? [];
        $currentStep = $this->workflow->current_step;

        return $steps[$currentStep - 1] ?? null;
    }
}

<?php

namespace App\Services;

use App\Models\FormWorkflow;
use App\Models\FormWorkflowStep;
use App\Models\Forms\FormInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * Start a workflow for a model.
     */
    public function startWorkflow(Model $model, string $workflowType, array $config = []): FormWorkflow
    {
        return DB::transaction(function () use ($model, $workflowType, $config) {
            // Create or get form instance for this model
            $formInstance = $this->createFormInstance($model, $workflowType);

            // Get workflow configuration
            $workflowConfig = $this->getWorkflowConfiguration($workflowType, $model);

            // Merge with provided config
            $finalConfig = array_merge($workflowConfig, $config);

            // Create the workflow
            $workflow = FormWorkflow::create([
                'form_instance_id' => $formInstance->id,
                'workflow_type' => $workflowType,
                'current_step' => 1,
                'total_steps' => count($finalConfig['steps'] ?? []),
                'steps_configuration_json' => $finalConfig,
                'status' => 'in_progress',
                'started_at' => now(),
                'tenant_id' => $model->tenant_id ?? null
            ]);

            // Create workflow steps
            $this->createWorkflowSteps($workflow, $finalConfig['steps'] ?? []);

            // Start the first step
            $this->startNextStep($workflow);

            return $workflow;
        });
    }

    /**
     * Create form instance for workflow.
     */
    private function createFormInstance(Model $model, string $workflowType): FormInstance
    {
        // Check if instance already exists
        $existingInstance = FormInstance::where('reference_type', get_class($model))
            ->where('reference_id', $model->id)
            ->where('form_type', $workflowType)
            ->first();

        if ($existingInstance) {
            return $existingInstance;
        }

        // Get or create default form template for this workflow type
        $formTemplate = $this->getOrCreateDefaultTemplate($workflowType, $model);

        // Create new form instance
        $formData = $this->extractFormData($model, $workflowType);

        return FormInstance::create([
            'tenant_id' => $model->tenant_id ?? null,
            'form_template_id' => $formTemplate->id,
            'user_id' => Auth::id(),
            'form_type' => $workflowType,
            'reference_type' => get_class($model),
            'reference_id' => $model->id,
            'form_data' => $formData,
            'status' => 'submitted',
            'created_by' => Auth::id(),
            'submitted_at' => now()
        ]);
    }

    /**
     * Extract relevant form data from the model.
     */
    private function extractFormData(Model $model, string $workflowType): array
    {
        $data = $model->toArray();

        // Remove sensitive or unnecessary fields
        $excludeFields = ['id', 'created_at', 'updated_at', 'deleted_at'];

        return array_diff_key($data, array_flip($excludeFields));
    }

    /**
     * Advance workflow to next step.
     */
    public function advanceWorkflow(FormWorkflow $workflow, string $decision, array $data = []): bool
    {
        $currentStep = $workflow->steps()
            ->where('step_number', $workflow->current_step)
            ->first();

        if (!$currentStep) {
            return false;
        }

        // Complete current step
        $currentStep->update([
            'status' => 'completed',
            'completed_at' => now(),
            'decision' => $decision,
            'comments' => $data['comments'] ?? null,
            'decision_by' => Auth::id(),
            'decision_date' => now()
        ]);

        // Check if workflow should continue
        if ($decision === 'rejected' || $decision === 'escalated') {
            return $this->handleWorkflowRejection($workflow, $decision, $data);
        }

        // Move to next step
        if ($workflow->current_step < $workflow->total_steps) {
            $workflow->update(['current_step' => $workflow->current_step + 1]);
            $this->startNextStep($workflow);
        } else {
            // Complete workflow
            $workflow->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
        }

        return true;
    }

    /**
     * Get workflow configuration for a type.
     */
    private function getWorkflowConfiguration(string $workflowType, Model $model): array
    {
        return match($workflowType) {
            'budget_approval' => $this->getBudgetApprovalWorkflow($model),
            'transaction_approval' => $this->getTransactionApprovalWorkflow($model),
            'project_approval' => $this->getProjectApprovalWorkflow($model),
            default => ['steps' => []]
        };
    }

    /**
     * Get budget approval workflow configuration.
     */
    private function getBudgetApprovalWorkflow(Model $budget): array
    {
        $amount = $budget->total_amount ?? 0;

        $steps = [
            [
                'step_number' => 1,
                'step_name' => 'Technical Review',
                'step_type' => 'review',
                'required_role' => 'program_manager',
                'instructions' => 'Review budget technical details and alignment with project objectives'
            ]
        ];

        // Add financial review for budgets over threshold
        if ($amount > 100000) {
            $steps[] = [
                'step_number' => 2,
                'step_name' => 'Financial Review',
                'step_type' => 'approval',
                'required_role' => 'finance_officer',
                'instructions' => 'Review budget financial details and compliance'
            ];
        }

        // Add director approval for large budgets
        if ($amount > 500000) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Director Approval',
                'step_type' => 'approval',
                'required_role' => 'org_admin',
                'instructions' => 'Final approval for large budget'
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * Get transaction approval workflow configuration.
     */
    private function getTransactionApprovalWorkflow(Model $transaction): array
    {
        $amount = $transaction->amount ?? 0;
        $steps = [];

        // Always require at least one approval step for transactions
        $steps[] = [
            'step_number' => 1,
            'step_name' => 'Manager Approval',
            'step_type' => 'approval',
            'required_role' => 'program_manager',
            'instructions' => 'Approve transaction for project'
        ];

        if ($amount > 10000) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Senior Manager Approval',
                'step_type' => 'approval',
                'required_role' => 'senior_manager',
                'instructions' => 'Senior manager approval for significant transaction'
            ];
        }

        if ($amount > 50000) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Finance Approval',
                'step_type' => 'approval',
                'required_role' => 'finance_officer',
                'instructions' => 'Financial approval for large transaction'
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * Get project approval workflow configuration.
     */
    private function getProjectApprovalWorkflow(Model $project): array
    {
        return [
            'steps' => [
                [
                    'step_number' => 1,
                    'step_name' => 'Technical Review',
                    'step_type' => 'review',
                    'required_role' => 'program_manager',
                    'instructions' => 'Review project technical feasibility and alignment'
                ],
                [
                    'step_number' => 2,
                    'step_name' => 'Financial Review',
                    'step_type' => 'review',
                    'required_role' => 'finance_officer',
                    'instructions' => 'Review project budget and financial sustainability'
                ],
                [
                    'step_number' => 3,
                    'step_name' => 'Final Approval',
                    'step_type' => 'approval',
                    'required_role' => 'org_admin',
                    'instructions' => 'Final project approval'
                ]
            ]
        ];
    }

    /**
     * Create workflow steps.
     */
    private function createWorkflowSteps(FormWorkflow $workflow, array $steps): void
    {
        foreach ($steps as $stepConfig) {
            FormWorkflowStep::create([
                'workflow_id' => $workflow->id,
                'step_number' => $stepConfig['step_number'],
                'step_name' => $stepConfig['step_name'],
                'step_type' => $stepConfig['step_type'],
                'required_role' => $stepConfig['required_role'],
                'instructions' => $stepConfig['instructions'] ?? null,
                'required_actions_json' => $stepConfig['required_actions'] ?? [],
                'form_modifications_allowed' => $stepConfig['form_modifications_allowed'] ?? false,
                'status' => $stepConfig['step_number'] === 1 ? 'in_progress' : 'pending'
            ]);
        }
    }

    /**
     * Start the next step in the workflow.
     */
    private function startNextStep(FormWorkflow $workflow): void
    {
        $nextStep = $workflow->steps()
            ->where('step_number', $workflow->current_step)
            ->first();

        if ($nextStep) {
            $nextStep->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);
        }
    }

    /**
     * Handle workflow rejection or escalation.
     */
    private function handleWorkflowRejection(FormWorkflow $workflow, string $decision, array $data): bool
    {
        if ($decision === 'rejected') {
            $workflow->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'escalation_reason' => $data['rejection_reason'] ?? 'Workflow rejected'
            ]);
        } elseif ($decision === 'escalated') {
            $workflow->update([
                'escalation_level' => ($workflow->escalation_level ?? 0) + 1,
                'escalated_at' => now(),
                'escalation_reason' => $data['escalation_reason'] ?? 'Workflow escalated'
            ]);

            // Create escalation step
            $this->createEscalationStep($workflow);
        }

        return true;
    }

    /**
     * Create escalation step.
     */
    private function createEscalationStep(FormWorkflow $workflow): void
    {
        $escalationStep = FormWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_number' => $workflow->total_steps + 1,
            'step_name' => 'Escalation Review',
            'step_type' => 'escalation',
            'required_role' => 'org_admin',
            'instructions' => 'Review escalated workflow',
            'status' => 'in_progress',
            'started_at' => now()
        ]);

        $workflow->update([
            'total_steps' => $workflow->total_steps + 1,
            'current_step' => $escalationStep->step_number
        ]);
    }

    /**
     * Get or create default form template for workflow type.
     */
    private function getOrCreateDefaultTemplate(string $workflowType, Model $model): \App\Models\Forms\FormTemplate
    {
        $templateName = $this->getTemplateNameForWorkflowType($workflowType);

        // Try to find existing template
        $template = \App\Models\Forms\FormTemplate::where('name', $templateName)
            ->where('tenant_id', $model->tenant_id)
            ->where('is_active', true)
            ->first();

        if ($template) {
            return $template;
        }

        // Create default template if not exists
        return $this->createDefaultTemplate($workflowType, $model);
    }

    /**
     * Get template name for workflow type.
     */
    private function getTemplateNameForWorkflowType(string $workflowType): string
    {
        return match($workflowType) {
            'transaction_approval' => 'Transaction Approval Workflow',
            'budget_approval' => 'Budget Approval Workflow',
            'project_approval' => 'Project Approval Workflow',
            default => ucfirst(str_replace('_', ' ', $workflowType)) . ' Workflow'
        };
    }

    /**
     * Create default template for workflow type.
     */
    private function createDefaultTemplate(string $workflowType, Model $model): \App\Models\Forms\FormTemplate
    {
        $templateName = $this->getTemplateNameForWorkflowType($workflowType);
        $workflowConfig = $this->getWorkflowConfiguration($workflowType, $model);

        return \App\Models\Forms\FormTemplate::create([
            'tenant_id' => $model->tenant_id,
            'name' => $templateName,
            'description' => 'Default workflow template for ' . $workflowType,
            'category' => 'financial',
            'compliance_level' => 'standard',
            'version' => '1.0',
            'is_active' => true,
            'is_default' => true,
            'workflow_configuration' => $workflowConfig,
            'form_configuration' => [
                'type' => 'workflow',
                'workflow_type' => $workflowType,
                'auto_approve' => false,
                'require_comments' => true
            ],
            'steps' => [
                [
                    'step_number' => 1,
                    'step_name' => 'Default Step',
                    'sections' => [
                        [
                            'section_name' => 'Default Section',
                            'fields' => []
                        ]
                    ]
                ]
            ],
            'created_by' => Auth::id()
        ]);
    }
}

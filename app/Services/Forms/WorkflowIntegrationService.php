<?php 
// File: app/Services/Forms/WorkflowIntegrationService.php
namespace App\Services\Forms;

use App\Models\Forms\FormInstance;
use App\Models\Forms\FormTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WorkflowIntegrationService
{
    protected $ruleEngine;

    public function __construct(?FormRuleEngine $ruleEngine = null)
    {
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Initialize workflow for form instance
     */
    public function initializeWorkflow(FormInstance $formInstance): array
    {
        $template = $formInstance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        
        if (empty($workflowConfig)) {
            return ['state' => 'no_workflow', 'next_action' => 'submit'];
        }
        
        $initialState = [
            'workflow_type' => $workflowConfig['type'] ?? 'approval',
            'current_step' => $workflowConfig['initial_step'] ?? 'draft',
            'steps_completed' => [],
            'started_at' => now()->toISOString(),
            'metadata' => [
                'form_instance_id' => $formInstance->id,
                'template_id' => $template->id,
                'tenant_id' => $formInstance->tenant_id
            ]
        ];
        
        $formInstance->update(['workflow_state' => json_encode($initialState)]);
        
        return $initialState;
    }

    /**
     * Execute workflow step
     */
    public function executeWorkflowStep(FormInstance $formInstance, string $action, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $template = $formInstance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        $currentState = json_decode($formInstance->workflow_state ?? '{}', true);
        
        if (empty($workflowConfig) || empty($currentState)) {
            return ['success' => false, 'message' => 'No workflow configured'];
        }
        
        try {
            return DB::transaction(function () use ($formInstance, $action, $user, $workflowConfig, $currentState) {
                $result = $this->processWorkflowAction($formInstance, $action, $user, $workflowConfig, $currentState);
                
                if ($result['success']) {
                    // Update form instance
                    $formInstance->update([
                        'workflow_state' => json_encode($result['new_state']),
                        'status' => $result['form_status'] ?? $formInstance->status
                    ]);
                    
                    // Log workflow action
                    $this->logWorkflowAction($formInstance, $action, $user, $result);
                    
                    // Send notifications if needed
                    $this->sendWorkflowNotifications($formInstance, $result);
                }
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Workflow execution failed', [
                'form_instance_id' => $formInstance->id,
                'action' => $action,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Workflow execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Determine next workflow step
     */
    public function determineNextStep(FormInstance $formInstance): array
    {
        $template = $formInstance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        $currentState = json_decode($formInstance->workflow_state ?? '{}', true);
        
        if (empty($workflowConfig) || empty($currentState)) {
            return ['next_step' => null, 'available_actions' => []];
        }
        
        $currentStep = $currentState['current_step'] ?? 'draft';
        $steps = $workflowConfig['steps'] ?? [];
        
        $nextStep = $this->ruleEngine->determineNextStep($workflowConfig, $formInstance->form_data);
        $availableActions = $this->getAvailableActions($currentStep, $steps, $formInstance);
        
        return [
            'current_step' => $currentStep,
            'next_step' => $nextStep,
            'available_actions' => $availableActions,
            'can_proceed' => $this->canProceedToNextStep($formInstance, $nextStep)
        ];
    }

    /**
     * Check if workflow can be escalated
     */
    public function checkEscalation(FormInstance $formInstance): array
    {
        $template = $formInstance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        $currentState = json_decode($formInstance->workflow_state ?? '{}', true);
        
        $escalationRules = $workflowConfig['escalation_rules'] ?? [];
        $escalations = [];
        
        foreach ($escalationRules as $rule) {
            if ($this->shouldEscalate($rule, $currentState, $formInstance)) {
                $escalations[] = $this->createEscalation($rule, $formInstance);
            }
        }
        
        return $escalations;
    }

    private function processWorkflowAction(FormInstance $formInstance, string $action, User $user, array $workflowConfig, array $currentState): array
    {
        $currentStep = $currentState['current_step'] ?? 'draft';
        $steps = $workflowConfig['steps'] ?? [];
        
        // Find current step configuration
        $stepConfig = $this->findStepConfig($currentStep, $steps);
        
        if (!$stepConfig) {
            return ['success' => false, 'message' => 'Invalid workflow step'];
        }
        
        // Check if user can perform this action
        if (!$this->canUserPerformAction($user, $action, $stepConfig, $formInstance)) {
            return ['success' => false, 'message' => 'User not authorized for this action'];
        }
        
        // Process the action
        switch ($action) {
            case 'approve':
                return $this->processApproval($formInstance, $user, $stepConfig, $currentState);
                
            case 'reject':
                return $this->processRejection($formInstance, $user, $stepConfig, $currentState);
                
            case 'request_changes':
                return $this->processChangeRequest($formInstance, $user, $stepConfig, $currentState);
                
            case 'escalate':
                return $this->processEscalation($formInstance, $user, $stepConfig, $currentState);
                
            case 'submit':
                return $this->processSubmission($formInstance, $user, $stepConfig, $currentState);
                
            default:
                return ['success' => false, 'message' => 'Invalid workflow action'];
        }
    }

    private function canUserPerformAction(User $user, string $action, array $stepConfig, FormInstance $formInstance): bool
    {
        // Check if user is the form owner
        if ($formInstance->user_id === $user->id && in_array($action, ['submit', 'save'])) {
            return true;
        }
        
        // Check role-based permissions
        $requiredRole = $stepConfig['approver_role'] ?? null;
        if ($requiredRole && !$user->hasTenantRole($requiredRole)) {
            return false;
        }
        
        // Check specific permissions
        $requiredPermissions = $stepConfig['required_permissions'] ?? [];
        foreach ($requiredPermissions as $permission) {
            if (!$user->hasTenantPermission($permission)) {
                return false;
            }
        }
        
        // Check workflow-specific conditions
        $conditions = $stepConfig['conditions'] ?? [];
        foreach ($conditions as $condition) {
            if (!$this->ruleEngine->evaluateCondition($condition, $formInstance->form_data)) {
                return false;
            }
        }
        
        return true;
    }

    private function processApproval(FormInstance $formInstance, User $user, array $stepConfig, array $currentState): array
    {
        $newState = $currentState;
        $newState['steps_completed'][] = [
            'step' => $currentState['current_step'],
            'action' => 'approved',
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
            'notes' => request()->input('notes')
        ];
        
        // Determine next step
        $nextStep = $this->getNextApprovalStep($stepConfig, $formInstance);
        
        if ($nextStep) {
            $newState['current_step'] = $nextStep;
            $formStatus = 'under_review';
        } else {
            // Workflow complete
            $newState['current_step'] = 'completed';
            $newState['completed_at'] = now()->toISOString();
            $formStatus = 'approved';
        }
        
        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => $formStatus,
            'message' => 'Form approved successfully',
            'next_step' => $nextStep
        ];
    }

    private function processRejection(FormInstance $formInstance, User $user, array $stepConfig, array $currentState): array
    {
        $reason = request()->input('reason', 'No reason provided');
        
        $newState = $currentState;
        $newState['steps_completed'][] = [
            'step' => $currentState['current_step'],
            'action' => 'rejected',
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
            'reason' => $reason
        ];
        
        $newState['current_step'] = 'rejected';
        $newState['rejected_at'] = now()->toISOString();
        
        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => 'rejected',
            'message' => 'Form rejected',
            'reason' => $reason
        ];
    }

    private function processChangeRequest(FormInstance $formInstance, User $user, array $stepConfig, array $currentState): array
    {
        $requestedChanges = request()->input('requested_changes', []);
        
        $newState = $currentState;
        $newState['steps_completed'][] = [
            'step' => $currentState['current_step'],
            'action' => 'changes_requested',
            'user_id' => $user->id,
            'timestamp' => now()->toISOString(),
            'requested_changes' => $requestedChanges
        ];
        
        $newState['current_step'] = 'draft';
        
        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => 'draft',
            'message' => 'Changes requested',
            'requested_changes' => $requestedChanges
        ];
    }

    private function processEscalation(FormInstance $formInstance, User $user, array $stepConfig, array $currentState): array
    {
        $escalationReason = request()->input('escalation_reason', 'Manual escalation');
        
        $newState = $currentState;
        $newState['escalations'][] = [
            'from_step' => $currentState['current_step'],
            'escalated_by' => $user->id,
            'timestamp' => now()->toISOString(),
            'reason' => $escalationReason
        ];
        
        // Find escalation target
        $escalationTarget = $stepConfig['escalation_target'] ?? 'supervisor';
        $newState['current_step'] = $escalationTarget;
        
        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => 'under_review',
            'message' => 'Form escalated',
            'escalation_target' => $escalationTarget
        ];
    }

    private function processSubmission(FormInstance $formInstance, User $user, array $stepConfig, array $currentState): array
    {
        // Validate form before submission
        $intelligence = app(FormIntelligenceService::class);
        $validation = $intelligence->validateFormData($formInstance->form_data, $formInstance->template);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Form has validation errors',
                'validation_errors' => $validation['errors']
            ];
        }
        
        $newState = $currentState;
        $newState['steps_completed'][] = [
            'step' => $currentState['current_step'],
            'action' => 'submitted',
            'user_id' => $user->id,
            'timestamp' => now()->toISOString()
        ];
        
        // Determine first approval step
        $nextStep = $this->getFirstApprovalStep($stepConfig, $formInstance);
        
        if ($nextStep) {
            $newState['current_step'] = $nextStep;
            $formStatus = 'submitted';
        } else {
            // Auto-approve if no approval workflow
            $newState['current_step'] = 'completed';
            $newState['completed_at'] = now()->toISOString();
            $formStatus = 'approved';
        }
        
        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => $formStatus,
            'message' => 'Form submitted successfully',
            'next_step' => $nextStep
        ];
    }

    private function findStepConfig(string $stepName, array $steps): ?array
    {
        foreach ($steps as $step) {
            if (($step['step_name'] ?? '') === $stepName) {
                return $step;
            }
        }
        return null;
    }

    private function getNextApprovalStep(array $currentStepConfig, FormInstance $formInstance): ?string
    {
        $workflowConfig = $formInstance->template->workflow_configuration ?? [];
        $steps = $workflowConfig['steps'] ?? [];
        
        $currentStepName = $currentStepConfig['step_name'] ?? '';
        $currentIndex = -1;
        
        // Find current step index
        foreach ($steps as $index => $step) {
            if (($step['step_name'] ?? '') === $currentStepName) {
                $currentIndex = $index;
                break;
            }
        }
        
        // Find next applicable step
        for ($i = $currentIndex + 1; $i < count($steps); $i++) {
            $step = $steps[$i];
            
            // Check if step conditions are met
            $conditions = $step['conditions'] ?? '';
            if ($conditions && !$this->ruleEngine->evaluateCondition($conditions, $formInstance->form_data)) {
                continue; // Skip this step
            }
            
            return $step['step_name'] ?? null;
        }
        
        return null; // No more steps
    }

    private function getFirstApprovalStep(array $stepConfig, FormInstance $formInstance): ?string
    {
        $workflowConfig = $formInstance->template->workflow_configuration ?? [];
        $steps = $workflowConfig['steps'] ?? [];
        
        foreach ($steps as $step) {
            $conditions = $step['conditions'] ?? '';
            if ($conditions && !$this->ruleEngine->evaluateCondition($conditions, $formInstance->form_data)) {
                continue; // Skip this step
            }
            
            return $step['step_name'] ?? null;
        }
        
        return null;
    }

    private function getAvailableActions(string $currentStep, array $steps, FormInstance $formInstance): array
    {
        $stepConfig = $this->findStepConfig($currentStep, $steps);
        $user = auth()->user();
        
        if (!$stepConfig) {
            return [];
        }
        
        $actions = [];
        
        // Standard actions based on step type
        $possibleActions = $stepConfig['available_actions'] ?? ['approve', 'reject', 'request_changes'];
        
        foreach ($possibleActions as $action) {
            if ($this->canUserPerformAction($user, $action, $stepConfig, $formInstance)) {
                $actions[] = [
                    'action' => $action,
                    'label' => ucwords(str_replace('_', ' ', $action)),
                    'requires_comment' => in_array($action, ['reject', 'request_changes', 'escalate'])
                ];
            }
        }
        
        return $actions;
    }

    private function canProceedToNextStep(FormInstance $formInstance, ?string $nextStep): bool
    {
        if (!$nextStep) {
            return true; // No next step means workflow can complete
        }
        
        // Check if form is valid for next step
        $intelligence = app(FormIntelligenceService::class);
        $validation = $intelligence->validateFormData($formInstance->form_data, $formInstance->template);
        
        return $validation['valid'];
    }

    private function shouldEscalate(array $rule, array $currentState, FormInstance $formInstance): bool
    {
        $trigger = $rule['trigger'] ?? '';
        
        switch ($trigger) {
            case 'sla_breach':
                return $this->checkSLABreach($rule, $currentState);
                
            case 'manual_escalation':
                return false; // Manual escalations are handled separately
                
            case 'budget_threshold':
                $budget = (float) ($formInstance->form_data['budget'] ?? 0);
                $threshold = (float) ($rule['threshold'] ?? 0);
                return $budget > $threshold;
                
            default:
                return false;
        }
    }

    private function checkSLABreach(array $rule, array $currentState): bool
    {
        $slaHours = (int) ($rule['sla_hours'] ?? 72);
        $currentStepStarted = $currentState['step_started_at'] ?? $currentState['started_at'] ?? null;
        
        if (!$currentStepStarted) {
            return false;
        }
        
        $stepStartTime = new \DateTime($currentStepStarted);
        $now = new \DateTime();
        $hoursElapsed = $stepStartTime->diff($now)->h + ($stepStartTime->diff($now)->days * 24);
        
        return $hoursElapsed > $slaHours;
    }

    private function createEscalation(array $rule, FormInstance $formInstance): array
    {
        return [
            'type' => $rule['trigger'],
            'escalate_to' => $rule['escalate_to'] ?? 'supervisor',
            'reason' => $rule['reason'] ?? 'SLA breach detected',
            'form_instance_id' => $formInstance->id,
            'created_at' => now()->toISOString()
        ];
    }

    private function logWorkflowAction(FormInstance $formInstance, string $action, User $user, array $result): void
    {
        $history = $formInstance->workflow_history ?? [];
        $history[] = [
            'action' => $action,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'timestamp' => now()->toISOString(),
            'result' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'] ?? '',
            'metadata' => [
                'previous_step' => $result['previous_step'] ?? null,
                'next_step' => $result['next_step'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]
        ];
        
        $formInstance->update(['workflow_history' => $history]);
    }

    private function sendWorkflowNotifications(FormInstance $formInstance, array $result): void
    {
        // Implementation would depend on notification service
        // This is a placeholder for workflow notifications
        
        $notifications = [];
        
        if ($result['success'] && isset($result['next_step'])) {
            // Notify next approver
            $nextStep = $result['next_step'];
            $approvers = $this->getStepApprovers($nextStep, $formInstance);
            
            foreach ($approvers as $approver) {
                $notifications[] = [
                    'type' => 'workflow_approval_required',
                    'user_id' => $approver->id,
                    'form_instance_id' => $formInstance->id,
                    'message' => "Form '{$formInstance->template->name}' requires your approval"
                ];
            }
        }
        
        if (!empty($notifications)) {
            // Queue notifications for sending
            // dispatch(new SendWorkflowNotifications($notifications));
        }
    }

    private function getStepApprovers(string $stepName, FormInstance $formInstance): array
    {
        $workflowConfig = $formInstance->template->workflow_configuration ?? [];
        $steps = $workflowConfig['steps'] ?? [];
        
        $stepConfig = $this->findStepConfig($stepName, $steps);
        
        if (!$stepConfig) {
            return [];
        }
        
        $approverRole = $stepConfig['approver_role'] ?? null;
        
        if (!$approverRole) {
            return [];
        }
        
        // Get users with the required role in the current tenant
        return User::whereHas('tenants', function ($query) use ($formInstance, $approverRole) {
            $query->where('tenants.id', $formInstance->tenant_id)
                  ->whereHas('roles', function ($roleQuery) use ($approverRole) {
                      $roleQuery->where('name', $approverRole);
                  });
        })->get()->toArray();
    }
}
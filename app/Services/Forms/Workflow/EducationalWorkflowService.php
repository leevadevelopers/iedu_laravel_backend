<?php

namespace App\Services\Forms\Workflow;

use App\Models\Forms\FormInstance;
use App\Models\Forms\FormTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
// use App\Notifications\EducationalWorkflowNotification; // TODO: Create this notification class

class EducationalWorkflowService
{
    /**
     * Initialize educational workflow for form instance
     */
    public function initializeEducationalWorkflow(FormInstance $formInstance): array
    {
        $template = $formInstance->template;
        $category = $template->category;

        $workflowConfig = $this->getEducationalWorkflowConfig($category);

        if (empty($workflowConfig)) {
            return ['state' => 'no_workflow', 'next_action' => 'submit'];
        }

        $initialState = [
            'workflow_type' => 'educational',
            'category' => $category,
            'current_step' => $workflowConfig['initial_step'] ?? 'draft',
            'steps_completed' => [],
            'started_at' => now()->toISOString(),
            'metadata' => [
                'form_instance_id' => $formInstance->id,
                'template_id' => $template->id,
                'tenant_id' => $formInstance->tenant_id,
                'academic_year' => $formInstance->form_data['academic_year'] ?? null,
                'school_code' => $formInstance->form_data['school_code'] ?? null
            ]
        ];

        $formInstance->update(['workflow_state' => json_encode($initialState)]);

        return $initialState;
    }

    /**
     * Get educational workflow configuration based on category
     */
    private function getEducationalWorkflowConfig(string $category): array
    {
        $configs = [
            'student_enrollment' => [
                'type' => 'approval',
                'initial_step' => 'draft',
                'steps' => [
                    'draft' => ['name' => 'Draft', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Under Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_info']],
                    'needs_info' => ['name' => 'Needs Information', 'can_edit' => true, 'next_steps' => ['review']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => []],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['enrollment_officer', 'principal', 'health_officer'],
                'sla_hours' => 72
            ],

            'student_registration' => [
                'type' => 'simple',
                'initial_step' => 'draft',
                'steps' => [
                    'draft' => ['name' => 'Draft', 'can_edit' => true, 'next_steps' => ['submitted']],
                    'submitted' => ['name' => 'Submitted', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => [],
                'sla_hours' => 24
            ],

            'attendance' => [
                'type' => 'monitoring',
                'initial_step' => 'recorded',
                'steps' => [
                    'recorded' => ['name' => 'Recorded', 'can_edit' => true, 'next_steps' => ['verified']],
                    'verified' => ['name' => 'Verified', 'can_edit' => false, 'next_steps' => ['follow_up']],
                    'follow_up' => ['name' => 'Follow-up Required', 'can_edit' => true, 'next_steps' => ['resolved']],
                    'resolved' => ['name' => 'Resolved', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['teacher', 'attendance_officer'],
                'sla_hours' => 48
            ],

            'grades' => [
                'type' => 'approval',
                'initial_step' => 'draft',
                'steps' => [
                    'draft' => ['name' => 'Draft', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Under Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['review']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['published']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'published' => ['name' => 'Published', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['teacher', 'department_head', 'principal'],
                'sla_hours' => 96
            ],

            'behavior_incident' => [
                'type' => 'escalation',
                'initial_step' => 'reported',
                'steps' => [
                    'reported' => ['name' => 'Reported', 'can_edit' => true, 'next_steps' => ['investigation']],
                    'investigation' => ['name' => 'Under Investigation', 'can_edit' => false, 'next_steps' => ['disciplinary_review']],
                    'disciplinary_review' => ['name' => 'Disciplinary Review', 'can_edit' => false, 'next_steps' => ['action_taken', 'dismissed']],
                    'action_taken' => ['name' => 'Action Taken', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'dismissed' => ['name' => 'Dismissed', 'can_edit' => false, 'next_steps' => []],
                    'monitoring' => ['name' => 'Under Monitoring', 'can_edit' => false, 'next_steps' => ['resolved']],
                    'resolved' => ['name' => 'Resolved', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['teacher', 'counselor', 'principal', 'district_officer'],
                'sla_hours' => 24
            ],

            'special_education' => [
                'type' => 'approval',
                'initial_step' => 'assessment',
                'steps' => [
                    'assessment' => ['name' => 'Assessment', 'can_edit' => true, 'next_steps' => ['iep_development']],
                    'iep_development' => ['name' => 'IEP Development', 'can_edit' => true, 'next_steps' => ['parent_review']],
                    'parent_review' => ['name' => 'Parent Review', 'can_edit' => false, 'next_steps' => ['iep_approved', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['parent_review']],
                    'iep_approved' => ['name' => 'IEP Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['review']],
                    'review' => ['name' => 'Annual Review', 'can_edit' => true, 'next_steps' => ['iep_development']]
                ],
                'approvers' => ['special_education_teacher', 'psychologist', 'principal', 'district_specialist'],
                'sla_hours' => 168
            ],

            'field_trip' => [
                'type' => 'approval',
                'initial_step' => 'planning',
                'steps' => [
                    'planning' => ['name' => 'Planning', 'can_edit' => true, 'next_steps' => ['risk_assessment']],
                    'risk_assessment' => ['name' => 'Risk Assessment', 'can_edit' => true, 'next_steps' => ['principal_approval']],
                    'principal_approval' => ['name' => 'Principal Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['risk_assessment']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['parent_consent']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'parent_consent' => ['name' => 'Parent Consent', 'can_edit' => false, 'next_steps' => ['final_approval']],
                    'final_approval' => ['name' => 'Final Approval', 'can_edit' => false, 'next_steps' => ['execution']],
                    'execution' => ['name' => 'Execution', 'can_edit' => false, 'next_steps' => ['post_trip_report']],
                    'post_trip_report' => ['name' => 'Post-Trip Report', 'can_edit' => true, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['teacher', 'principal', 'district_safety_officer'],
                'sla_hours' => 120
            ]
        ];

        return $configs[$category] ?? [];
    }

    /**
     * Execute educational workflow step
     */
    public function executeEducationalWorkflowStep(FormInstance $formInstance, string $action, ?User $user = null): array
    {
        $user = $user ?? \Illuminate\Support\Facades\Auth::user();
        $template = $formInstance->template;
        $category = $template->category;
        $workflowConfig = $this->getEducationalWorkflowConfig($category);
        $currentState = json_decode($formInstance->workflow_state ?? '{}', true);

        if (empty($workflowConfig) || empty($currentState)) {
            return ['success' => false, 'message' => 'No educational workflow configured'];
        }

        try {
            return DB::transaction(function () use ($formInstance, $action, $user, $workflowConfig, $currentState) {
                $result = $this->processEducationalWorkflowAction($formInstance, $action, $user, $workflowConfig, $currentState);

                if ($result['success']) {
                    // Update form instance
                    $formInstance->update([
                        'workflow_state' => json_encode($result['new_state']),
                        'status' => $result['form_status'] ?? $formInstance->status
                    ]);

                    // Log workflow action
                    $this->logEducationalWorkflowAction($formInstance, $action, $user, $result);

                    // Send notifications
                    $this->sendEducationalWorkflowNotifications($formInstance, $result, $user);

                    // Check SLA compliance
                    $this->checkSLACompliance($formInstance, $workflowConfig);
                }

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Educational workflow execution failed', [
                'form_instance_id' => $formInstance->id,
                'action' => $action,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Educational workflow execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process educational workflow action
     */
    private function processEducationalWorkflowAction(FormInstance $formInstance, string $action, User $user, array $workflowConfig, array $currentState): array
    {
        $currentStep = $currentState['current_step'];
        $steps = $workflowConfig['steps'];

        if (!isset($steps[$currentStep])) {
            return ['success' => false, 'message' => 'Invalid current step'];
        }

        $currentStepConfig = $steps[$currentStep];
        $nextSteps = $currentStepConfig['next_steps'];

        if (!in_array($action, $nextSteps)) {
            return ['success' => false, 'message' => 'Action not allowed for current step'];
        }

        // Check user permissions for this action
        if (!$this->canUserPerformAction($user, $action, $workflowConfig, $currentState)) {
            return ['success' => false, 'message' => 'User not authorized for this action'];
        }

        // Update workflow state
        $newState = $currentState;
        $newState['current_step'] = $action;
        $newState['steps_completed'][] = [
            'step' => $currentStep,
            'completed_at' => now()->toISOString(),
            'completed_by' => $user->id,
            'action' => $action
        ];
        $newState['last_action'] = [
            'action' => $action,
            'performed_by' => $user->id,
            'performed_at' => now()->toISOString()
        ];

        // Determine form status
        $formStatus = $this->determineFormStatus($action, $workflowConfig);

        return [
            'success' => true,
            'new_state' => $newState,
            'form_status' => $formStatus,
            'message' => "Successfully moved to step: {$action}",
            'next_available_actions' => $steps[$action]['next_steps'] ?? []
        ];
    }

    /**
     * Check if user can perform specific action
     */
    private function canUserPerformAction(User $user, string $action, array $workflowConfig, array $currentState): bool
    {
        $approvers = $workflowConfig['approvers'] ?? [];

        // Check user roles
        $userRoles = $user->roles->pluck('name')->toArray();

        foreach ($approvers as $approverRole) {
            if (in_array($approverRole, $userRoles)) {
                return true;
            }
        }

        // Check if user is the form creator
        if ($currentState['metadata']['created_by'] ?? null === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine form status based on workflow step
     */
    private function determineFormStatus(string $step, array $workflowConfig): string
    {
        $statusMapping = [
            'draft' => 'draft',
            'submitted' => 'submitted',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'completed' => 'completed',
            'published' => 'completed',
            'resolved' => 'completed'
        ];

        return $statusMapping[$step] ?? 'in_progress';
    }

    /**
     * Log educational workflow action
     */
    private function logEducationalWorkflowAction(FormInstance $formInstance, string $action, User $user, array $result): void
    {
        Log::info('Educational workflow action executed', [
            'form_instance_id' => $formInstance->id,
            'template_category' => $formInstance->template->category,
            'action' => $action,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'new_step' => $result['new_state']['current_step'],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Send educational workflow notifications
     */
    private function sendEducationalWorkflowNotifications(FormInstance $formInstance, array $result, User $user): void
    {
        try {
            $template = $formInstance->template;
            $category = $template->category;
            $newStep = $result['new_state']['current_step'];

            // Get users to notify based on workflow step
            $usersToNotify = $this->getUsersToNotify($category, $newStep, $formInstance);

            if (!empty($usersToNotify)) {
                foreach ($usersToNotify as $userToNotify) {
                    // TODO: Implement EducationalWorkflowNotification
                    // $userToNotify->notify(new EducationalWorkflowNotification(
                    //     $formInstance,
                    //     $newStep,
                    //     $user,
                    //     $result
                    // ));
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send educational workflow notifications', [
                'form_instance_id' => $formInstance->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get users to notify for specific workflow step
     */
    private function getUsersToNotify(string $category, string $step, FormInstance $formInstance): array
    {
        // This would be implemented based on your user management system
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Check SLA compliance for educational workflow
     */
    private function checkSLACompliance(FormInstance $formInstance, array $workflowConfig): void
    {
        $slaHours = $workflowConfig['sla_hours'] ?? 72;
        $startedAt = json_decode($formInstance->workflow_state, true)['started_at'] ?? null;

        if ($startedAt) {
            $startTime = \Carbon\Carbon::parse($startedAt);
            $elapsedHours = $startTime->diffInHours(now());

            if ($elapsedHours > $slaHours) {
                Log::warning('Educational workflow SLA exceeded', [
                    'form_instance_id' => $formInstance->id,
                    'template_category' => $formInstance->template->category,
                    'sla_hours' => $slaHours,
                    'elapsed_hours' => $elapsedHours
                ]);

                // Send escalation notification
                $this->sendSLAEscalationNotification($formInstance, $slaHours, $elapsedHours);
            }
        }
    }

    /**
     * Send SLA escalation notification
     */
    private function sendSLAEscalationNotification(FormInstance $formInstance, int $slaHours, int $elapsedHours): void
    {
        // This would be implemented to send escalation notifications
        // For now, just log the escalation
        Log::warning('SLA escalation required', [
            'form_instance_id' => $formInstance->id,
            'template_category' => $formInstance->template->category,
            'sla_hours' => $slaHours,
            'elapsed_hours' => $elapsedHours
        ]);
    }

    /**
     * Get educational workflow status
     */
    public function getEducationalWorkflowStatus(FormInstance $formInstance): array
    {
        $workflowState = json_decode($formInstance->workflow_state ?? '{}', true);
        $template = $formInstance->template;
        $category = $template->category;
        $workflowConfig = $this->getEducationalWorkflowConfig($category);

        if (empty($workflowConfig)) {
            return ['status' => 'no_workflow'];
        }

        $currentStep = $workflowState['current_step'] ?? 'unknown';
        $stepConfig = $workflowConfig['steps'][$currentStep] ?? [];

        return [
            'status' => 'active',
            'workflow_type' => $workflowConfig['type'],
            'category' => $category,
            'current_step' => $currentStep,
            'current_step_name' => $stepConfig['name'] ?? 'Unknown',
            'can_edit' => $stepConfig['can_edit'] ?? false,
            'next_available_steps' => $stepConfig['next_steps'] ?? [],
            'steps_completed' => $workflowState['steps_completed'] ?? [],
            'started_at' => $workflowState['started_at'] ?? null,
            'last_action' => $workflowState['last_action'] ?? null,
            'sla_hours' => $workflowConfig['sla_hours'],
            'approvers' => $workflowConfig['approvers']
        ];
    }
}

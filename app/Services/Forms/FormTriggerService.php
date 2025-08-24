<?php

namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FormTriggerService
{
    /**
     * Execute triggers for a form event
     */
    public function executeTriggers(FormInstance $formInstance, string $event, array $context = []): array
    {
        $template = $formInstance->template;
        $triggers = $template->form_triggers ?? [];
        
        if (empty($triggers)) {
            return ['executed' => 0, 'results' => []];
        }

        $results = [];
        $executedCount = 0;

        foreach ($triggers as $trigger) {
            if ($this->shouldExecuteTrigger($trigger, $event, $context)) {
                try {
                    $result = $this->executeTrigger($trigger, $formInstance, $context);
                    $results[] = [
                        'trigger_id' => $trigger['id'] ?? uniqid(),
                        'event' => $event,
                        'action' => $trigger['action'],
                        'success' => $result['success'],
                        'message' => $result['message'],
                        'timestamp' => now()->toISOString()
                    ];
                    
                    if ($result['success']) {
                        $executedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error('Form trigger execution failed', [
                        'trigger' => $trigger,
                        'form_instance_id' => $formInstance->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    $results[] = [
                        'trigger_id' => $trigger['id'] ?? uniqid(),
                        'event' => $event,
                        'action' => $trigger['action'],
                        'success' => false,
                        'message' => 'Trigger execution failed: ' . $e->getMessage(),
                        'timestamp' => now()->toISOString()
                    ];
                }
            }
        }

        return [
            'executed' => $executedCount,
            'total' => count($triggers),
            'results' => $results
        ];
    }

    /**
     * Check if a trigger should be executed
     */
    protected function shouldExecuteTrigger(array $trigger, string $event, array $context): bool
    {
        // Check if trigger is active
        if (isset($trigger['is_active']) && !$trigger['is_active']) {
            return false;
        }

        // Check event match
        if ($trigger['trigger_event'] !== $event) {
            return false;
        }

        // Check conditions if specified
        if (isset($trigger['conditions']) && !empty($trigger['conditions'])) {
            return $this->evaluateConditions($trigger['conditions'], $context);
        }

        return true;
    }

    /**
     * Evaluate trigger conditions
     */
    protected function evaluateConditions(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

        if (empty($field) || !isset($context['form_data'][$field])) {
            return false;
        }

        $fieldValue = $context['form_data'][$field];

        switch ($operator) {
            case 'equals':
                return $fieldValue == $value;
            case 'not_equals':
                return $fieldValue != $value;
            case 'greater_than':
                return (float)$fieldValue > (float)$value;
            case 'less_than':
                return (float)$fieldValue < (float)$value;
            case 'greater_than_or_equal':
                return (float)$fieldValue >= (float)$value;
            case 'less_than_or_equal':
                return (float)$fieldValue <= (float)$value;
            case 'contains':
                return str_contains((string)$fieldValue, (string)$value);
            case 'not_contains':
                return !str_contains((string)$fieldValue, (string)$value);
            case 'in':
                return in_array($fieldValue, (array)$value);
            case 'not_in':
                return !in_array($fieldValue, (array)$value);
            case 'is_empty':
                return empty($fieldValue);
            case 'is_not_empty':
                return !empty($fieldValue);
            default:
                return false;
        }
    }

    /**
     * Execute a single trigger
     */
    protected function executeTrigger(array $trigger, FormInstance $formInstance, array $context): array
    {
        $action = $trigger['action'] ?? '';
        $parameters = $trigger['parameters'] ?? [];

        try {
            switch ($action) {
                case 'notify':
                    return $this->executeNotification($trigger, $formInstance, $context);
                
                case 'webhook_call':
                    return $this->executeWebhookCall($trigger, $formInstance, $context);
                
                case 'escalate_approval':
                    return $this->executeApprovalEscalation($trigger, $formInstance, $context);
                
                case 'auto_approve':
                    return $this->executeAutoApproval($trigger, $formInstance, $context);
                
                case 'update_status':
                    return $this->executeStatusUpdate($trigger, $formInstance, $context);
                
                default:
                    return [
                        'success' => false,
                        'message' => "Unknown action: {$action}"
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Trigger execution failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Trigger execution failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute notification action
     */
    protected function executeNotification(array $trigger, FormInstance $formInstance, array $context): array
    {
        $targets = $trigger['targets'] ?? [];
        $message = $trigger['message'] ?? 'Form trigger notification';

        if (empty($targets)) {
            return [
                'success' => false,
                'message' => 'No notification targets specified'
            ];
        }

        try {
            // Get users based on targets
            $users = $this->getUsersByTargets($targets, $formInstance);
            
            if (empty($users)) {
                return [
                    'success' => false,
                    'message' => 'No users found for notification targets'
                ];
            }

            // Send email notifications to each user
            $emailController = app(\App\Http\Controllers\Notification\EmailController::class);
            $successCount = 0;
            $failedEmails = [];

            foreach ($users as $user) {
                if (isset($user['email']) && !empty($user['email'])) {
                    try {
                        // Create email request
                        $emailRequest = new \Illuminate\Http\Request();
                        $emailRequest->merge([
                            'email' => $user['email'],
                            'subject' => $this->generateEmailSubject($trigger, $formInstance),
                            'message' => $this->generateEmailMessage($trigger, $formInstance, $user, $context)
                        ]);

                        // Send email
                        $response = $emailController->sendEmail($emailRequest);
                        
                        if ($response->getStatusCode() === 200) {
                            $successCount++;
                        } else {
                            $failedEmails[] = $user['email'];
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to send email notification', [
                            'user_email' => $user['email'],
                            'error' => $e->getMessage(),
                            'form_instance_id' => $formInstance->id
                        ]);
                        $failedEmails[] = $user['email'];
                    }
                }
            }

            // Log notification results
            Log::info('Form trigger email notifications sent', [
                'message' => $message,
                'targets' => $targets,
                'users_count' => count($users),
                'success_count' => $successCount,
                'failed_emails' => $failedEmails,
                'form_instance_id' => $formInstance->id
            ]);

            $resultMessage = "Email notifications sent to {$successCount} users";
            if (!empty($failedEmails)) {
                $resultMessage .= " (Failed: " . implode(', ', $failedEmails) . ")";
            }

            return [
                'success' => $successCount > 0,
                'message' => $resultMessage,
                'details' => [
                    'total_users' => count($users),
                    'success_count' => $successCount,
                    'failed_emails' => $failedEmails
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Form trigger notification failed', [
                'error' => $e->getMessage(),
                'form_instance_id' => $formInstance->id
            ]);
            
            return [
                'success' => false,
                'message' => 'Notification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate email subject for form trigger notification
     */
    protected function generateEmailSubject(array $trigger, FormInstance $formInstance): string
    {
        $templateName = $formInstance->template->name ?? 'Form';
        $eventLabel = $this->getEventLabel($trigger['trigger_event'] ?? 'form_trigger');
        
        return "[IPM System] {$eventLabel} - {$templateName}";
    }

    /**
     * Generate email message for form trigger notification
     */
    protected function generateEmailMessage(array $trigger, FormInstance $formInstance, array $user, array $context): string
    {
        $templateName = $formInstance->template->name ?? 'Form';
        $eventLabel = $this->getEventLabel($trigger['trigger_event'] ?? 'form_trigger');
        $userName = $user['name'] ?? 'User';
        $formCode = $formInstance->instance_code ?? $formInstance->id;
        
        $message = $trigger['message'] ?? "A form trigger notification has been activated.";
        
        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .content { background-color: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
                .footer { margin-top: 20px; padding: 20px; background-color: #f8f9fa; border-radius: 5px; font-size: 12px; color: #6c757d; }
                .highlight { background-color: #fff3cd; padding: 10px; border-radius: 3px; border-left: 4px solid #ffc107; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>IPM System Notification</h2>
                    <p><strong>Event:</strong> {$eventLabel}</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$userName},</p>
                    
                    <div class='highlight'>
                        {$message}
                    </div>
                    
                    <p><strong>Form Details:</strong></p>
                    <ul>
                        <li><strong>Form Name:</strong> {$templateName}</li>
                        <li><strong>Form Code:</strong> {$formCode}</li>
                        <li><strong>Submitted By:</strong> " . ($formInstance->user->name ?? 'Unknown') . "</li>
                        <li><strong>Submitted At:</strong> " . ($formInstance->submitted_at ?? 'N/A') . "</li>
                    </ul>
                    
                    <p>Please log into the IPM system to review this form submission.</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated notification from the IPM System.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";

        return $htmlMessage;
    }

    /**
     * Get human-readable event label
     */
    protected function getEventLabel(string $event): string
    {
        $events = self::getAvailableEvents();
        return $events[$event] ?? ucfirst(str_replace('_', ' ', $event));
    }

    /**
     * Execute webhook call
     */
    protected function executeWebhookCall(array $trigger, FormInstance $formInstance, array $context): array
    {
        try {
            $webhookUrl = $trigger['webhook_url'] ?? '';
            $webhookSecret = $trigger['webhook_secret'] ?? '';
            
            if (empty($webhookUrl)) {
                return [
                    'success' => false,
                    'message' => 'Webhook URL not specified'
                ];
            }

            // Prepare webhook data
            $webhookData = [
                'event' => $context['event'] ?? 'form_trigger',
                'form_instance_id' => $formInstance->id,
                'form_template_id' => $formInstance->template_id,
                'trigger_data' => $trigger,
                'timestamp' => now()->toISOString()
            ];

            // Make HTTP request to webhook
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Secret' => $webhookSecret
            ])->post($webhookUrl, $webhookData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Webhook called successfully to {$webhookUrl}"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Webhook call failed with status: {$response->status()}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Webhook call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute approval escalation
     */
    protected function executeApprovalEscalation(array $trigger, FormInstance $formInstance, array $context): array
    {
        try {
            $parameters = $trigger['parameters'] ?? [];
            $escalateTo = $parameters['escalate_to'] ?? 'supervisor';
            $reason = $parameters['reason'] ?? 'Automatic escalation triggered';

            // Update form instance workflow state
            $formInstance->update([
                'workflow_state' => array_merge($formInstance->workflow_state ?? [], [
                    'escalated' => true,
                    'escalated_to' => $escalateTo,
                    'escalation_reason' => $reason,
                    'escalated_at' => now()->toISOString()
                ])
            ]);

            return [
                'success' => true,
                'message' => "Form escalated to {$escalateTo}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Approval escalation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute auto approval
     */
    protected function executeAutoApproval(array $trigger, FormInstance $formInstance, array $context): array
    {
        try {
            $parameters = $trigger['parameters'] ?? [];
            $approvalLevel = $parameters['approval_level'] ?? 'basic';

            // Update form instance status
            $formInstance->update([
                'status' => 'approved',
                'workflow_state' => array_merge($formInstance->workflow_state ?? [], [
                    'auto_approved' => true,
                    'approval_level' => $approvalLevel,
                    'approved_at' => now()->toISOString()
                ])
            ]);

            return [
                'success' => true,
                'message' => "Form auto-approved at {$approvalLevel} level"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Auto approval failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute status update
     */
    protected function executeStatusUpdate(array $trigger, FormInstance $formInstance, array $context): array
    {
        try {
            $parameters = $trigger['parameters'] ?? [];
            $newStatus = $parameters['status'] ?? 'in_progress';
            $reason = $parameters['reason'] ?? 'Status updated by trigger';

            // Update form instance status
            $formInstance->update([
                'status' => $newStatus,
                'workflow_state' => array_merge($formInstance->workflow_state ?? [], [
                    'status_updated_by_trigger' => true,
                    'status_update_reason' => $reason,
                    'status_updated_at' => now()->toISOString()
                ])
            ]);

            return [
                'success' => true,
                'message' => "Form status updated to {$newStatus}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Status update failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get users by notification targets
     */
    protected function getUsersByTargets(array $targets, FormInstance $formInstance): array
    {
        $users = [];

        foreach ($targets as $target) {
            // Check if target is an email address
            if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
                // Create a virtual user object for email address
                $users[] = [
                    'id' => null,
                    'name' => 'Email Recipient',
                    'email' => $target,
                    'role' => 'email_recipient'
                ];
                continue;
            }

            switch ($target) {
                case 'supervisor_tecnico':
                    $users = array_merge($users, User::whereHas('roles', function($query) {
                        $query->where('name', 'supervisor_tecnico');
                    })->get()->toArray());
                    break;
                case 'equipa_emergencia':
                    $users = array_merge($users, User::whereHas('roles', function($query) {
                        $query->where('name', 'equipa_emergencia');
                    })->get()->toArray());
                    break;
                case 'coordenador_municipal':
                    $users = array_merge($users, User::whereHas('roles', function($query) {
                        $query->where('name', 'coordenador_municipal');
                    })->get()->toArray());
                    break;
                case 'form_creator':
                    if ($formInstance->created_by) {
                        $creator = User::find($formInstance->created_by);
                        if ($creator) {
                            $users[] = $creator->toArray();
                        }
                    }
                    break;
                case 'form_approver':
                    // Get users who can approve this form
                    $approvers = $this->getFormApprovers($formInstance);
                    $users = array_merge($users, $approvers);
                    break;
                default:
                    // Try to find users by role name using Spatie roles
                    $roleUsers = User::whereHas('roles', function($query) use ($target) {
                        $query->where('name', $target);
                    })->get()->toArray();
                    $users = array_merge($users, $roleUsers);
                    break;
            }
        }

        // Remove duplicates and null values
        $users = array_filter(array_unique($users, SORT_REGULAR));
        
        return $users;
    }

    /**
     * Get form approvers
     */
    protected function getFormApprovers(FormInstance $formInstance): array
    {
        $template = $formInstance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        $steps = $workflowConfig['steps'] ?? [];
        
        $approvers = [];
        
        foreach ($steps as $step) {
            if (isset($step['role']) && $step['role'] !== 'user') {
                $roleUsers = User::whereHas('roles', function($query) use ($step) {
                    $query->where('name', $step['role']);
                })->get()->toArray();
                $approvers = array_merge($approvers, $roleUsers);
            }
        }
        
        return array_unique($approvers, SORT_REGULAR);
    }

    /**
     * Get available trigger events
     */
    public static function getAvailableEvents(): array
    {
        return [
            'form_submitted' => 'Form Submitted',
            'form_approved' => 'Form Approved',
            'form_rejected' => 'Form Rejected',
            'risk_level_high' => 'Risk Level High',
            'risk_level_medium' => 'Risk Level Medium',
            'risk_level_low' => 'Risk Level Low',
            'budget_threshold_exceeded' => 'Budget Threshold Exceeded',
            'timeout' => 'Approval Timeout',
            'field_value_changed' => 'Field Value Changed',
            'workflow_step_completed' => 'Workflow Step Completed',
            'compliance_violation' => 'Compliance Violation',
            'deadline_approaching' => 'Deadline Approaching',
            'deadline_passed' => 'Deadline Passed'
        ];
    }

    /**
     * Get available trigger actions
     */
    public static function getAvailableActions(): array
    {
        return [
            'notify' => 'Send Notification',
            'webhook_call' => 'Call Webhook',
            'escalate_approval' => 'Escalate Approval',
            'auto_approve' => 'Auto Approve',
            'update_status' => 'Update Status'
        ];
    }

    /**
     * Get available condition operators
     */
    public static function getAvailableOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_than_or_equal' => 'Greater Than or Equal',
            'less_than_or_equal' => 'Less Than or Equal',
            'contains' => 'Contains',
            'not_contains' => 'Not Contains',
            'in' => 'In List',
            'not_in' => 'Not In List',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty'
        ];
    }
}

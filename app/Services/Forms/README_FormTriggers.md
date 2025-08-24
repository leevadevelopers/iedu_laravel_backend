# Form Triggers System - iOPS

## Overview

The Form Triggers system in iOPS allows you to automatically execute actions when specific events occur in your forms. This system provides a powerful way to automate workflows, notifications, and integrations without manual intervention.

## How It Works

### 1. Trigger Events
Triggers are activated by specific events:

- **Form Events**: `form_submitted`, `form_approved`, `form_rejected`
- **Risk Events**: `risk_level_high`, `risk_level_medium`, `risk_level_low`
- **Business Events**: `budget_threshold_exceeded`, `timeout`
- **Workflow Events**: `workflow_step_completed`, `field_value_changed`
- **Compliance Events**: `compliance_violation`, `deadline_approaching`

### 2. Trigger Actions
When a trigger fires, it can execute various actions:

- **notify**: Send notifications to specific users or roles
- **webhook_call**: Call external webhooks with form data
- **escalate_approval**: Escalate approval to higher levels
- **auto_approve**: Automatically approve forms under certain conditions
- **update_status**: Update form status automatically

### 3. Trigger Conditions
Triggers can have conditions that must be met:

- **Field Conditions**: Check if field values meet specific criteria
- **Operators**: `equals`, `greater_than`, `contains`, `is_empty`, etc.
- **Multiple Conditions**: Combine conditions with AND logic

## Example Usage

### Example 1: Risk Assessment Notification
```json
{
  "id": "risk_notification_001",
  "name": "High Risk Alert",
  "description": "Notify emergency team when risk level is high",
  "trigger_event": "risk_level_high",
  "action": "notify",
  "targets": ["equipa_emergencia", "coordenador_municipal"],
  "message": "Atenção: risco alto identificado neste projeto. Ação imediata necessária.",
  "is_active": true
}
```

### Example 2: Budget Threshold Webhook
```json
{
  "id": "budget_webhook_001",
  "name": "Budget Threshold Alert",
  "description": "Call external system when budget exceeds threshold",
  "trigger_event": "budget_threshold_exceeded",
  "action": "webhook_call",
  "webhook_url": "https://api.financeiro.gov.mz/webhooks/budget-alert",
  "webhook_secret": "secret_key_123",
  "conditions": [
    {
      "field": "budget_amount",
      "operator": "greater_than",
      "value": 1000000
    }
  ],
  "is_active": true
}
```

### Example 3: Auto-Approval for Low-Risk Projects
```json
{
  "id": "auto_approval_001",
  "name": "Low Risk Auto-Approval",
  "description": "Automatically approve low-risk projects",
  "trigger_event": "risk_level_low",
  "action": "auto_approve",
  "parameters": {
    "approval_level": "basic"
  },
  "conditions": [
    {
      "field": "risk_level",
      "operator": "equals",
      "value": "low"
    },
    {
      "field": "budget_amount",
      "operator": "less_than",
      "value": 500000
    }
  ],
  "is_active": true
}
```

## Implementation

### Backend Service
The `FormTriggerService` handles:
- Trigger execution
- Condition evaluation
- Action execution
- User notification
- Webhook calls
- Status updates

### Frontend Integration
The form builder includes:
- Trigger configuration interface
- Event and action selection
- Condition builder
- Trigger management (add/edit/delete)

## Configuration

### 1. Add Triggers to Form Template
```php
$formTemplate->form_triggers = [
    // Your trigger configurations here
];
```

### 2. Execute Triggers
```php
$triggerService = app(FormTriggerService::class);
$result = $triggerService->executeTriggers($formInstance, 'form_submitted', [
    'form_data' => $formData,
    'event' => 'form_submitted'
]);
```

### 3. Monitor Results
```php
if ($result['executed'] > 0) {
    Log::info('Triggers executed', $result);
}
```

## Best Practices

1. **Keep Triggers Simple**: One trigger per action for easier debugging
2. **Use Descriptive Names**: Clear naming helps with maintenance
3. **Test Conditions**: Verify trigger conditions work as expected
4. **Monitor Execution**: Log trigger results for troubleshooting
5. **Handle Errors**: Implement proper error handling in actions

## Security Considerations

- **Webhook Security**: Use secrets for external webhook calls
- **User Permissions**: Verify user roles before sending notifications
- **Data Validation**: Validate trigger conditions and parameters
- **Rate Limiting**: Implement rate limiting for webhook calls

## Future Enhancements

- **Advanced Conditions**: OR logic, nested conditions
- **Scheduled Triggers**: Time-based trigger execution
- **Trigger Chains**: Sequential trigger execution
- **Conditional Actions**: Different actions based on conditions
- **Trigger Templates**: Pre-configured trigger sets for common scenarios

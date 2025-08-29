<?php

namespace App\Jobs\Transport;

use App\Models\V1\Transport\TransportIncident;
use App\Models\FormInstance;
use App\Models\FormTemplate;
use App\Models\FormWorkflow;
// use App\Services\Forms\FormEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateIncidentWorkflow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected $incident;

    public function __construct(TransportIncident $incident)
    {
        $this->incident = $incident;
    }

    public function handle()
    {
        try {
            Log::info('Creating incident workflow', [
                'incident_id' => $this->incident->id,
                'incident_type' => $this->incident->incident_type,
                'severity' => $this->incident->severity
            ]);

            // Check if Forms Engine is available
            // if (!class_exists(\App\Services\Forms\FormEngineService::class)) {
            //     Log::info('Forms Engine not available, skipping workflow creation', [
            //         'incident_id' => $this->incident->id
            //     ]);
            //     return;
            // }

            // Get the appropriate workflow template based on incident type and severity
            $workflowTemplate = $this->getWorkflowTemplate();

            if (!$workflowTemplate) {
                Log::warning('No workflow template found for incident', [
                    'incident_id' => $this->incident->id,
                    'incident_type' => $this->incident->incident_type,
                    'severity' => $this->incident->severity
                ]);
                return;
            }

            // Create the workflow instance
            $workflow = $this->createWorkflowInstance($workflowTemplate);

            // Initialize the first step
            $this->initializeWorkflowStep($workflow);

            Log::info('Successfully created incident workflow', [
                'incident_id' => $this->incident->id,
                'workflow_id' => $workflow->id,
                'template_id' => $workflowTemplate->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create incident workflow', [
                'incident_id' => $this->incident->id,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    private function getWorkflowTemplate(): ?FormWorkflow
    {
        // Determine the appropriate workflow template based on incident characteristics
        $templateName = $this->determineTemplateName();

        return FormWorkflow::where('name', $templateName)
            ->where('status', 'active')
            ->first();
    }

    private function determineTemplateName(): string
    {
        $incidentType = $this->incident->incident_type;
        $severity = $this->incident->severity;

        // Map incident types to workflow templates
        return match($incidentType) {
            'accident' => $this->getAccidentTemplateName($severity),
            'breakdown' => $this->getBreakdownTemplateName($severity),
            'behavioral' => $this->getBehavioralTemplateName($severity),
            'medical' => $this->getMedicalTemplateName($severity),
            'delay' => $this->getDelayTemplateName($severity),
            'emergency' => 'Transport Emergency Response Workflow',
            default => $this->getDefaultTemplateName($severity)
        };
    }

    private function getAccidentTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Accident Response Workflow',
            'high' => 'Major Accident Investigation Workflow',
            'medium' => 'Minor Accident Report Workflow',
            'low' => 'Accident Documentation Workflow',
            default => 'Accident Response Workflow'
        };
    }

    private function getBreakdownTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Bus Breakdown Workflow',
            'high' => 'Major Breakdown Response Workflow',
            'medium' => 'Breakdown Maintenance Workflow',
            'low' => 'Breakdown Documentation Workflow',
            default => 'Bus Breakdown Workflow'
        };
    }

    private function getBehavioralTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Behavioral Incident Workflow',
            'high' => 'Major Behavioral Incident Workflow',
            'medium' => 'Behavioral Incident Investigation Workflow',
            'low' => 'Behavioral Incident Report Workflow',
            default => 'Behavioral Incident Workflow'
        };
    }

    private function getMedicalTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Medical Emergency Workflow',
            'high' => 'Medical Incident Response Workflow',
            'medium' => 'Medical Incident Documentation Workflow',
            'low' => 'Medical Incident Report Workflow',
            default => 'Medical Incident Workflow'
        };
    }

    private function getDelayTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Delay Response Workflow',
            'high' => 'Major Delay Management Workflow',
            'medium' => 'Delay Notification Workflow',
            'low' => 'Delay Documentation Workflow',
            default => 'Transport Delay Workflow'
        };
    }

    private function getDefaultTemplateName(string $severity): string
    {
        return match($severity) {
            'critical' => 'Critical Incident Response Workflow',
            'high' => 'High Priority Incident Workflow',
            'medium' => 'Standard Incident Workflow',
            'low' => 'Incident Documentation Workflow',
            default => 'Transport Incident Workflow'
        };
    }

    private function createWorkflowInstance(FormWorkflow $workflowTemplate): FormInstance
    {
        // Create the form instance for this workflow
        $formInstance = FormInstance::create([
            'school_id' => $this->incident->school_id,
            'form_template_id' => $workflowTemplate->form_template_id,
            'form_workflow_id' => $workflowTemplate->id,
            'title' => "Incident Response: {$this->incident->title}",
            'description' => $this->incident->description,
            'status' => 'in_progress',
            'metadata' => [
                'incident_id' => $this->incident->id,
                'incident_type' => $this->incident->incident_type,
                'severity' => $this->incident->severity,
                'reported_by' => $this->incident->reported_by,
                'reported_at' => $this->incident->incident_datetime->toISOString(),
                'workflow_type' => 'transport_incident',
                'auto_created' => true
            ],
            'created_by' => $this->incident->reported_by,
            'assigned_to' => $this->getInitialAssignee()
        ]);

        // Link the form instance to the incident
        $this->incident->update([
            'metadata' => array_merge(
                $this->incident->metadata ?? [],
                ['form_instance_id' => $formInstance->id]
            )
        ]);

        return $formInstance;
    }

    private function getInitialAssignee(): ?int
    {
        // Determine the initial assignee based on incident characteristics
        $incidentType = $this->incident->incident_type;
        $severity = $this->incident->severity;

        // For critical incidents, assign to transport manager or admin
        if ($severity === 'critical') {
            $manager = \App\Models\User::role('transport-manager')->first();
            if ($manager) {
                return $manager->id;
            }

            $admin = \App\Models\User::role('transport-admin')->first();
            if ($admin) {
                return $admin->id;
            }
        }

        // For breakdown incidents, assign to maintenance staff
        if ($incidentType === 'breakdown') {
            $maintenanceStaff = \App\Models\User::role('maintenance')->first();
            if ($maintenanceStaff) {
                return $maintenanceStaff->id;
            }
        }

        // For safety-related incidents, assign to safety officer
        if (in_array($incidentType, ['accident', 'behavioral', 'medical'])) {
            $safetyOfficer = \App\Models\User::role('safety-officer')->first();
            if ($safetyOfficer) {
                return $safetyOfficer->id;
            }
        }

        // Default to transport admin
        $transportAdmin = \App\Models\User::role('transport-admin')->first();
        return $transportAdmin?->id;
    }

    private function initializeWorkflowStep(FormInstance $workflow): void
    {
        try {
            // Get the first step of the workflow
            $firstStep = $workflow->formWorkflow->steps()
                ->orderBy('step_order')
                ->first();

            if (!$firstStep) {
                Log::warning('No workflow steps found', [
                    'workflow_id' => $workflow->id
                ]);
                return;
            }

            // Initialize the first step
            $workflow->update([
                'current_step_id' => $firstStep->id,
                'current_step_status' => 'pending'
            ]);

            // Create step instance if needed
            $this->createStepInstance($workflow, $firstStep);

            Log::info('Initialized workflow step', [
                'workflow_id' => $workflow->id,
                'step_id' => $firstStep->id,
                'step_name' => $firstStep->name
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize workflow step', [
                'workflow_id' => $workflow->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function createStepInstance(FormInstance $workflow, $step): void
    {
        // This would create the actual step instance in the Forms Engine
        // For now, just log the action
        Log::info('Creating step instance', [
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'step_name' => $step->name
        ]);

        // In a full implementation, this would:
        // 1. Create the step instance
        // 2. Set up any required forms or data collection
        // 3. Notify the assigned user
        // 4. Set up any automated actions or timers
    }
}

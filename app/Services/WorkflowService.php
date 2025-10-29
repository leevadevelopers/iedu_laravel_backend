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
     * Create a workflow without a specific model.
     */
    public function createWorkflow(array $workflowData): FormWorkflow
    {
        // Create a temporary model for the workflow
        $tempModel = new class extends Model {
            public $tenant_id;

            public function __construct() {
                parent::__construct();
                $user = auth('api')->user();
                $this->tenant_id = $user?->tenant_id ?? null;
            }
        };

        return $this->startWorkflow($tempModel, $workflowData['workflow_type'], $workflowData);
    }

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

                $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('User must be authenticated to create form instance');
        }

        // Ensure we have a valid tenant_id
        $tenantId = $model->tenant_id ?? null;

        // Fallback: Try to get tenant_id through school relationship (for models like FamilyRelationship)
        if (!$tenantId && method_exists($model, 'school') && $model->relationLoaded('school')) {
            $tenantId = $model->school?->tenant_id;
        }
        
        // Fallback: Try to get tenant_id through SchoolContextService
        if (!$tenantId && Auth::check()) {
            try {
                $schoolContextService = app(\App\Services\SchoolContextService::class);
                $tenantId = $schoolContextService->getCurrentTenantId();
            } catch (\Exception $e) {
                // Ignore if service fails
            }
        }

        if (!$tenantId) {
            throw new \Exception('Model must have a valid tenant_id to create form instance. Could not determine tenant_id from model or context.');
        }

        return FormInstance::create([
            'tenant_id' => $tenantId,
            'form_template_id' => $formTemplate->id,
            'user_id' => $user->id,
            'form_type' => $workflowType,
            'reference_type' => get_class($model),
            'reference_id' => $model->id,
            'form_data' => $formData,
            'status' => 'submitted',
            'created_by' => $user->id,
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
     * Get workflow by ID.
     */
    public function getWorkflow(int $workflowId): ?FormWorkflow
    {
        return FormWorkflow::find($workflowId);
    }

    /**
     * Process workflow approval.
     */
    public function processApproval(int $workflowId, string $action, string $comments = null): bool
    {
        $workflow = $this->getWorkflow($workflowId);

        if (!$workflow) {
            return false;
        }

        return $this->advanceWorkflow($workflow, $action, ['comments' => $comments]);
    }

    /**
     * Advance workflow to next step.
     */
    public function advanceWorkflow(FormWorkflow $workflow, string $decision, array $data = []): bool
    {
        $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('User must be authenticated to advance workflow');
        }

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
            'decision_by' => $user->id,
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
            'school_setup' => $this->getSchoolSetupWorkflow($model),
            'academic_year_setup' => $this->getAcademicYearSetupWorkflow($model),
            'grade_approval' => $this->getGradeApprovalWorkflow($model),
            'attendance_approval' => $this->getAttendanceApprovalWorkflow($model),
            'behavior_incident_approval' => $this->getBehaviorIncidentApprovalWorkflow($model),
            default => ['steps' => []]
        };
    }

    /**
     * Get grade approval workflow configuration.
     */
    private function getGradeApprovalWorkflow(Model $grade): array
    {
        $gradeValue = $grade->grade_value ?? 0;

        $steps = [
            [
                'step_number' => 1,
                'step_name' => 'Teacher Review',
                'step_type' => 'review',
                'required_role' => 'teacher',
                'instructions' => 'Review grade calculation and justification'
            ]
        ];

        // Add department head review for failing grades
        if ($gradeValue < 60) {
            $steps[] = [
                'step_number' => 2,
                'step_name' => 'Department Head Review',
                'step_type' => 'approval',
                'required_role' => 'department_head',
                'instructions' => 'Review failing grade and provide recommendations'
            ];
        }

        // Add principal approval for very low grades
        if ($gradeValue < 50) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Principal Approval',
                'step_type' => 'approval',
                'required_role' => 'principal',
                'instructions' => 'Final approval for very low grade'
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * Get attendance approval workflow configuration.
     */
    private function getAttendanceApprovalWorkflow(Model $attendance): array
    {
        $absenceCount = $attendance->absence_count ?? 0;
        $steps = [];

        // Always require teacher approval for attendance
        $steps[] = [
            'step_number' => 1,
            'step_name' => 'Teacher Approval',
            'step_type' => 'approval',
            'required_role' => 'teacher',
            'instructions' => 'Approve attendance record and note any discrepancies'
        ];

        if ($absenceCount > 5) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Counselor Review',
                'step_type' => 'approval',
                'required_role' => 'counselor',
                'instructions' => 'Review attendance pattern and provide guidance'
            ];
        }

        if ($absenceCount > 10) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Administrator Approval',
                'step_type' => 'approval',
                'required_role' => 'administrator',
                'instructions' => 'Review excessive absences and determine next steps'
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * Get behavior incident approval workflow configuration.
     */
    private function getBehaviorIncidentApprovalWorkflow(Model $incident): array
    {
        $severity = $incident->severity_level ?? 'low';
        $steps = [];

        // Always require teacher documentation
        $steps[] = [
            'step_number' => 1,
            'step_name' => 'Teacher Documentation',
            'step_type' => 'review',
            'required_role' => 'teacher',
            'instructions' => 'Document behavior incident with details and evidence'
        ];

        // Add counselor review for moderate incidents
        if (in_array($severity, ['moderate', 'high'])) {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Counselor Review',
                'step_type' => 'review',
                'required_role' => 'counselor',
                'instructions' => 'Review incident and provide behavioral intervention recommendations'
            ];
        }

        // Add administrator approval for high severity incidents
        if ($severity === 'high') {
            $steps[] = [
                'step_number' => count($steps) + 1,
                'step_name' => 'Administrator Approval',
                'step_type' => 'approval',
                'required_role' => 'administrator',
                'instructions' => 'Review high severity incident and determine disciplinary action'
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * Get school setup workflow configuration.
     */
    private function getSchoolSetupWorkflow(Model $school): array
    {
        $steps = [
            [
                'step_number' => 1,
                'step_name' => 'Initial Setup',
                'step_type' => 'setup',
                'required_role' => 'school_admin',
                'instructions' => 'Complete initial school configuration and setup'
            ],
            [
                'step_number' => 2,
                'step_name' => 'Staff Assignment',
                'step_type' => 'assignment',
                'required_role' => 'school_admin',
                'instructions' => 'Assign staff members and define roles'
            ],
            [
                'step_number' => 3,
                'step_name' => 'Curriculum Setup',
                'step_type' => 'setup',
                'required_role' => 'curriculum_coordinator',
                'instructions' => 'Configure curriculum and academic programs'
            ],
            [
                'step_number' => 4,
                'step_name' => 'Final Approval',
                'step_type' => 'approval',
                'required_role' => 'principal',
                'instructions' => 'Final review and approval of school setup'
            ]
        ];

        return ['steps' => $steps];
    }

    /**
     * Get academic year setup workflow configuration.
     */
    private function getAcademicYearSetupWorkflow(Model $academicYear): array
    {
        $steps = [
            [
                'step_number' => 1,
                'step_name' => 'Initial Setup',
                'step_type' => 'setup',
                'required_role' => 'school_admin',
                'instructions' => 'Complete initial academic year configuration'
            ],
            [
                'step_number' => 2,
                'step_name' => 'Term Planning',
                'step_type' => 'planning',
                'required_role' => 'academic_coordinator',
                'instructions' => 'Plan and configure academic terms and periods'
            ],
            [
                'step_number' => 3,
                'step_name' => 'Curriculum Setup',
                'step_type' => 'setup',
                'required_role' => 'curriculum_coordinator',
                'instructions' => 'Configure curriculum and course offerings for the academic year'
            ],
            [
                'step_number' => 4,
                'step_name' => 'Staff Assignment',
                'step_type' => 'assignment',
                'required_role' => 'school_admin',
                'instructions' => 'Assign staff and teachers to academic year activities'
            ],
            [
                'step_number' => 5,
                'step_name' => 'Final Approval',
                'step_type' => 'approval',
                'required_role' => 'principal',
                'instructions' => 'Final review and approval of academic year setup'
            ]
        ];

        return ['steps' => $steps];
    }

    /**
     * Create workflow steps.
     */
    private function createWorkflowSteps(FormWorkflow $workflow, array $steps): void
    {
        foreach ($steps as $index => $stepConfig) {
            // Validate step configuration
            if (!is_array($stepConfig)) {
                throw new \InvalidArgumentException("Step configuration at index {$index} must be an array, got " . gettype($stepConfig));
            }

            // Ensure required fields are present
            $requiredFields = ['step_number', 'step_name', 'step_type', 'required_role'];
            foreach ($requiredFields as $field) {
                if (!isset($stepConfig[$field])) {
                    throw new \InvalidArgumentException("Step configuration at index {$index} is missing required field: {$field}");
                }
            }

            FormWorkflowStep::create([
                'tenant_id' => $workflow->tenant_id,
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
            'tenant_id' => $workflow->tenant_id,
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
            'grade_approval' => 'Grade Approval Workflow',
            'attendance_approval' => 'Attendance Approval Workflow',
            'behavior_incident_approval' => 'Behavior Incident Approval Workflow',
            default => ucfirst(str_replace('_', ' ', $workflowType)) . ' Workflow'
        };
    }

    /**
     * Create default template for workflow type.
     */
    private function createDefaultTemplate(string $workflowType, Model $model): \App\Models\Forms\FormTemplate
    {
        $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('User must be authenticated to create form template');
        }

        $templateName = $this->getTemplateNameForWorkflowType($workflowType);
        $workflowConfig = $this->getWorkflowConfiguration($workflowType, $model);

        return \App\Models\Forms\FormTemplate::create([
            'tenant_id' => $model->tenant_id,
            'name' => $templateName,
            'description' => 'Default workflow template for ' . $workflowType,
            'category' => 'academic_records',
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
            'created_by' => $user->id
        ]);
    }
}

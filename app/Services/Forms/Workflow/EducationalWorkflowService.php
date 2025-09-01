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
            ],

            'school_registration' => [
                'type' => 'approval',
                'initial_step' => 'application',
                'steps' => [
                    'application' => ['name' => 'Application Submitted', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Under Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_info']],
                    'needs_info' => ['name' => 'Needs Information', 'can_edit' => true, 'next_steps' => ['review']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['registration']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'registration' => ['name' => 'Registration Complete', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['district_officer', 'education_board', 'compliance_officer'],
                'sla_hours' => 168
            ],

            'school_enrollment' => [
                'type' => 'approval',
                'initial_step' => 'planning',
                'steps' => [
                    'planning' => ['name' => 'Enrollment Planning', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Administrative Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['principal', 'district_officer', 'enrollment_coordinator'],
                'sla_hours' => 240
            ],

            'school_setup' => [
                'type' => 'project',
                'initial_step' => 'planning',
                'steps' => [
                    'planning' => ['name' => 'Planning Phase', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Board Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['construction']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'construction' => ['name' => 'Construction', 'can_edit' => false, 'next_steps' => ['inspection']],
                    'inspection' => ['name' => 'Inspection', 'can_edit' => false, 'next_steps' => ['certification']],
                    'certification' => ['name' => 'Certification', 'can_edit' => false, 'next_steps' => ['staffing']],
                    'staffing' => ['name' => 'Staffing', 'can_edit' => false, 'next_steps' => ['testing']],
                    'testing' => ['name' => 'Testing', 'can_edit' => false, 'next_steps' => ['operational']],
                    'operational' => ['name' => 'Operational', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['school_board', 'district_superintendent', 'construction_manager', 'principal'],
                'sla_hours' => 8760
            ],

            'staff_management' => [
                'type' => 'approval',
                'initial_step' => 'evaluation',
                'steps' => [
                    'evaluation' => ['name' => 'Performance Evaluation', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Supervisor Review', 'can_edit' => false, 'next_steps' => ['approved', 'needs_improvement', 'disciplinary']],
                    'needs_improvement' => ['name' => 'Needs Improvement', 'can_edit' => true, 'next_steps' => ['evaluation']],
                    'disciplinary' => ['name' => 'Disciplinary Action', 'can_edit' => false, 'next_steps' => ['resolution']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'resolution' => ['name' => 'Resolution', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['supervisor', 'hr_manager', 'principal'],
                'sla_hours' => 168
            ],

            'faculty_recruitment' => [
                'type' => 'approval',
                'initial_step' => 'application',
                'steps' => [
                    'application' => ['name' => 'Application Received', 'can_edit' => false, 'next_steps' => ['screening']],
                    'screening' => ['name' => 'Initial Screening', 'can_edit' => false, 'next_steps' => ['interview', 'rejected']],
                    'interview' => ['name' => 'Interview', 'can_edit' => false, 'next_steps' => ['reference_check', 'rejected']],
                    'reference_check' => ['name' => 'Reference Check', 'can_edit' => false, 'next_steps' => ['background_check', 'rejected']],
                    'background_check' => ['name' => 'Background Check', 'can_edit' => false, 'next_steps' => ['final_approval', 'rejected']],
                    'final_approval' => ['name' => 'Final Approval', 'can_edit' => false, 'next_steps' => ['offer', 'rejected']],
                    'offer' => ['name' => 'Offer Extended', 'can_edit' => false, 'next_steps' => ['accepted', 'declined']],
                    'accepted' => ['name' => 'Accepted', 'can_edit' => false, 'next_steps' => ['onboarding']],
                    'declined' => ['name' => 'Declined', 'can_edit' => false, 'next_steps' => []],
                    'onboarding' => ['name' => 'Onboarding', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['hr_manager', 'department_head', 'principal'],
                'sla_hours' => 336
            ],

            'professional_development' => [
                'type' => 'approval',
                'initial_step' => 'request',
                'steps' => [
                    'request' => ['name' => 'Development Request', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Supervisor Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['request']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['enrollment']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'enrollment' => ['name' => 'Enrolled', 'can_edit' => false, 'next_steps' => ['participation']],
                    'participation' => ['name' => 'Participation', 'can_edit' => false, 'next_steps' => ['completion']],
                    'completion' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Evaluation', 'can_edit' => false, 'next_steps' => ['certification']],
                    'certification' => ['name' => 'Certified', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['supervisor', 'hr_manager', 'principal'],
                'sla_hours' => 720
            ],

            'school_calendar' => [
                'type' => 'approval',
                'initial_step' => 'draft',
                'steps' => [
                    'draft' => ['name' => 'Calendar Draft', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Administrative Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['draft']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['published']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'published' => ['name' => 'Published', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['principal', 'district_officer', 'calendar_coordinator'],
                'sla_hours' => 168
            ],

            'events_management' => [
                'type' => 'approval',
                'initial_step' => 'proposal',
                'steps' => [
                    'proposal' => ['name' => 'Event Proposal', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Event Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['proposal']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['planning']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'planning' => ['name' => 'Event Planning', 'can_edit' => true, 'next_steps' => ['execution']],
                    'execution' => ['name' => 'Event Execution', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Post-Event Evaluation', 'can_edit' => true, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['event_coordinator', 'principal', 'facilities_manager'],
                'sla_hours' => 168
            ],

            'facilities_management' => [
                'type' => 'maintenance',
                'initial_step' => 'inspection',
                'steps' => [
                    'inspection' => ['name' => 'Facility Inspection', 'can_edit' => true, 'next_steps' => ['assessment']],
                    'assessment' => ['name' => 'Needs Assessment', 'can_edit' => false, 'next_steps' => ['maintenance', 'no_action']],
                    'maintenance' => ['name' => 'Maintenance Required', 'can_edit' => false, 'next_steps' => ['scheduling']],
                    'no_action' => ['name' => 'No Action Required', 'can_edit' => false, 'next_steps' => ['completed']],
                    'scheduling' => ['name' => 'Maintenance Scheduled', 'can_edit' => false, 'next_steps' => ['execution']],
                    'execution' => ['name' => 'Maintenance Execution', 'can_edit' => false, 'next_steps' => ['verification']],
                    'verification' => ['name' => 'Quality Verification', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['facilities_manager', 'maintenance_supervisor', 'principal'],
                'sla_hours' => 72
            ],

            'transportation' => [
                'type' => 'approval',
                'initial_step' => 'route_planning',
                'steps' => [
                    'route_planning' => ['name' => 'Route Planning', 'can_edit' => true, 'next_steps' => ['safety_review']],
                    'safety_review' => ['name' => 'Safety Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['route_planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Performance Evaluation', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['transportation_manager', 'safety_officer', 'principal'],
                'sla_hours' => 168
            ],

            'cafeteria_management' => [
                'type' => 'operational',
                'initial_step' => 'menu_planning',
                'steps' => [
                    'menu_planning' => ['name' => 'Menu Planning', 'can_edit' => true, 'next_steps' => ['nutrition_review']],
                    'nutrition_review' => ['name' => 'Nutrition Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_changes']],
                    'needs_changes' => ['name' => 'Needs Changes', 'can_edit' => true, 'next_steps' => ['menu_planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['procurement']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'procurement' => ['name' => 'Procurement', 'can_edit' => false, 'next_steps' => ['preparation']],
                    'preparation' => ['name' => 'Food Preparation', 'can_edit' => false, 'next_steps' => ['service']],
                    'service' => ['name' => 'Food Service', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Service Evaluation', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['cafeteria_manager', 'nutritionist', 'principal'],
                'sla_hours' => 48
            ],

            'library_management' => [
                'type' => 'operational',
                'initial_step' => 'acquisition',
                'steps' => [
                    'acquisition' => ['name' => 'Resource Acquisition', 'can_edit' => true, 'next_steps' => ['cataloging']],
                    'cataloging' => ['name' => 'Cataloging', 'can_edit' => false, 'next_steps' => ['processing']],
                    'processing' => ['name' => 'Processing', 'can_edit' => false, 'next_steps' => ['shelving']],
                    'shelving' => ['name' => 'Shelving', 'can_edit' => false, 'next_steps' => ['circulation']],
                    'circulation' => ['name' => 'Circulation', 'can_edit' => false, 'next_steps' => ['maintenance']],
                    'maintenance' => ['name' => 'Maintenance', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Collection Evaluation', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['librarian', 'library_assistant', 'principal'],
                'sla_hours' => 72
            ],

            'technology_management' => [
                'type' => 'technical',
                'initial_step' => 'assessment',
                'steps' => [
                    'assessment' => ['name' => 'Technology Assessment', 'can_edit' => true, 'next_steps' => ['planning']],
                    'planning' => ['name' => 'Implementation Planning', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Technical Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['testing']],
                    'testing' => ['name' => 'Testing', 'can_edit' => false, 'next_steps' => ['deployment']],
                    'deployment' => ['name' => 'Deployment', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['it_manager', 'technology_coordinator', 'principal'],
                'sla_hours' => 336
            ],

            'security_management' => [
                'type' => 'security',
                'initial_step' => 'incident_report',
                'steps' => [
                    'incident_report' => ['name' => 'Incident Report', 'can_edit' => true, 'next_steps' => ['investigation']],
                    'investigation' => ['name' => 'Investigation', 'can_edit' => false, 'next_steps' => ['assessment']],
                    'assessment' => ['name' => 'Risk Assessment', 'can_edit' => false, 'next_steps' => ['action_plan']],
                    'action_plan' => ['name' => 'Action Plan', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Security Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['action_plan']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['verification']],
                    'verification' => ['name' => 'Verification', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['security_officer', 'principal', 'district_security'],
                'sla_hours' => 24
            ],

            'maintenance_requests' => [
                'type' => 'maintenance',
                'initial_step' => 'request',
                'steps' => [
                    'request' => ['name' => 'Maintenance Request', 'can_edit' => true, 'next_steps' => ['assessment']],
                    'assessment' => ['name' => 'Assessment', 'can_edit' => false, 'next_steps' => ['scheduling']],
                    'scheduling' => ['name' => 'Scheduling', 'can_edit' => false, 'next_steps' => ['execution']],
                    'execution' => ['name' => 'Execution', 'can_edit' => false, 'next_steps' => ['verification']],
                    'verification' => ['name' => 'Quality Verification', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['maintenance_supervisor', 'facilities_manager', 'principal'],
                'sla_hours' => 72
            ],

            'financial_aid' => [
                'type' => 'approval',
                'initial_step' => 'application',
                'steps' => [
                    'application' => ['name' => 'Application Submitted', 'can_edit' => true, 'next_steps' => ['review']],
                    'review' => ['name' => 'Financial Review', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_info']],
                    'needs_info' => ['name' => 'Needs Information', 'can_edit' => true, 'next_steps' => ['review']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['disbursement']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'disbursement' => ['name' => 'Disbursement', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['financial_aid_officer', 'principal', 'district_finance'],
                'sla_hours' => 168
            ],

            'tuition_management' => [
                'type' => 'financial',
                'initial_step' => 'billing',
                'steps' => [
                    'billing' => ['name' => 'Billing Generated', 'can_edit' => true, 'next_steps' => ['payment']],
                    'payment' => ['name' => 'Payment Processing', 'can_edit' => false, 'next_steps' => ['verification']],
                    'verification' => ['name' => 'Payment Verification', 'can_edit' => false, 'next_steps' => ['reconciliation']],
                    'reconciliation' => ['name' => 'Account Reconciliation', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['finance_officer', 'accountant', 'principal'],
                'sla_hours' => 48
            ],

            'donation_management' => [
                'type' => 'approval',
                'initial_step' => 'donation',
                'steps' => [
                    'donation' => ['name' => 'Donation Received', 'can_edit' => false, 'next_steps' => ['acknowledgment']],
                    'acknowledgment' => ['name' => 'Acknowledgment', 'can_edit' => true, 'next_steps' => ['processing']],
                    'processing' => ['name' => 'Processing', 'can_edit' => false, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Administrative Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['utilization']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => ['return']],
                    'utilization' => ['name' => 'Utilization', 'can_edit' => false, 'next_steps' => ['reporting']],
                    'return' => ['name' => 'Return Process', 'can_edit' => false, 'next_steps' => ['completed']],
                    'reporting' => ['name' => 'Reporting', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['development_officer', 'principal', 'board_member'],
                'sla_hours' => 168
            ],

            'alumni_relations' => [
                'type' => 'engagement',
                'initial_step' => 'outreach',
                'steps' => [
                    'outreach' => ['name' => 'Alumni Outreach', 'can_edit' => true, 'next_steps' => ['engagement']],
                    'engagement' => ['name' => 'Engagement Activities', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Program Evaluation', 'can_edit' => false, 'next_steps' => ['improvement']],
                    'improvement' => ['name' => 'Program Improvement', 'can_edit' => true, 'next_steps' => ['outreach']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['alumni_coordinator', 'principal', 'development_officer'],
                'sla_hours' => 720
            ],

            'community_outreach' => [
                'type' => 'engagement',
                'initial_step' => 'planning',
                'steps' => [
                    'planning' => ['name' => 'Program Planning', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Administrative Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['planning']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Program Evaluation', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['community_coordinator', 'principal', 'district_officer'],
                'sla_hours' => 720
            ],

            'partnership_management' => [
                'type' => 'partnership',
                'initial_step' => 'exploration',
                'steps' => [
                    'exploration' => ['name' => 'Partnership Exploration', 'can_edit' => true, 'next_steps' => ['negotiation']],
                    'negotiation' => ['name' => 'Negotiation', 'can_edit' => true, 'next_steps' => ['agreement']],
                    'agreement' => ['name' => 'Agreement Drafting', 'can_edit' => true, 'next_steps' => ['approval']],
                    'approval' => ['name' => 'Legal Approval', 'can_edit' => false, 'next_steps' => ['approved', 'rejected', 'needs_revision']],
                    'needs_revision' => ['name' => 'Needs Revision', 'can_edit' => true, 'next_steps' => ['agreement']],
                    'approved' => ['name' => 'Approved', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'rejected' => ['name' => 'Rejected', 'can_edit' => false, 'next_steps' => []],
                    'implementation' => ['name' => 'Implementation', 'can_edit' => false, 'next_steps' => ['monitoring']],
                    'monitoring' => ['name' => 'Monitoring', 'can_edit' => false, 'next_steps' => ['evaluation']],
                    'evaluation' => ['name' => 'Partnership Evaluation', 'can_edit' => false, 'next_steps' => ['renewal']],
                    'renewal' => ['name' => 'Renewal Decision', 'can_edit' => false, 'next_steps' => ['renewed', 'terminated', 'completed']],
                    'renewed' => ['name' => 'Renewed', 'can_edit' => false, 'next_steps' => ['implementation']],
                    'terminated' => ['name' => 'Terminated', 'can_edit' => false, 'next_steps' => ['completed']],
                    'completed' => ['name' => 'Completed', 'can_edit' => false, 'next_steps' => []]
                ],
                'approvers' => ['partnership_coordinator', 'principal', 'legal_counsel', 'board_member'],
                'sla_hours' => 1680
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

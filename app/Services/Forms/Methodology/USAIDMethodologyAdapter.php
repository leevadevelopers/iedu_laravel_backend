<?php
// File: app/Services/Forms/Methodology/USAIDMethodologyAdapter.php
namespace App\Services\Forms\Methodology;

class USAIDMethodologyAdapter implements MethodologyAdapterInterface
{
    public function adaptTemplate(array $templateData): array
    {
        // Add USAID-specific sections and fields
        $templateData['steps'] = $this->addUSAIDRequiredSections($templateData['steps'] ?? []);
        
        // Add USAID validation rules
        $templateData['validation_rules'] = array_merge(
            $templateData['validation_rules'] ?? [],
            $this->getUSAIDValidationRules()
        );
        
        // Add USAID workflow configuration
        $templateData['workflow_configuration'] = array_merge(
            $templateData['workflow_configuration'] ?? [],
            $this->getUSAIDWorkflowConfiguration()
        );
        
        // Set compliance level
        $templateData['compliance_level'] = 'strict';
        
        return $templateData;
    }

    public function getRequirements(): array
    {
        return [
            'environmental_screening' => [
                'mandatory' => true,
                'description' => 'Environmental screening required for all USAID projects',
                'trigger_conditions' => ['budget > 50000', 'category = construction']
            ],
            'gender_integration' => [
                'mandatory' => true,
                'description' => 'Gender integration analysis required',
                'minimum_requirements' => ['gender_analysis', 'gender_indicators']
            ],
            'marking_branding' => [
                'mandatory' => true,
                'description' => 'USAID marking and branding requirements',
                'requirements' => ['usaid_logo', 'branding_plan']
            ]
        ];
    }

    public function getComplianceConfiguration(): array
    {
        return [
            'required_approvals' => ['technical_officer', 'agreement_officer', 'contracting_officer'],
            'sla_requirements' => ['ao_review' => '5 days', 'co_approval' => '7 days'],
            'budget_thresholds' => [
                'level_1' => 100000,
                'level_2' => 500000,
                'level_3' => 1000000
            ]
        ];
    }

    private function addUSAIDRequiredSections(array $steps): array
    {
        // Find or create compliance step
        $complianceStepExists = false;
        foreach ($steps as $step) {
            if (($step['step_id'] ?? '') === 'usaid_compliance') {
                $complianceStepExists = true;
                break;
            }
        }

        if (!$complianceStepExists) {
            $steps[] = [
                'step_id' => 'usaid_compliance',
                'step_number' => count($steps) + 1,
                'step_title' => 'USAID Compliance Requirements',
                'step_description' => 'Complete USAID-specific compliance requirements',
                'icon' => 'pi pi-shield',
                'estimated_time' => '30 minutes',
                'is_required' => true,
                'methodology_specific' => true,
                'sections' => [
                    [
                        'section_id' => 'environmental_compliance',
                        'section_title' => 'Environmental Screening',
                        'section_description' => 'Environmental impact assessment and screening',
                        'collapsible' => false,
                        'methodology_adaptation' => true,
                        'fields' => [
                            [
                                'field_id' => 'environmental_screening',
                                'field_type' => 'dropdown',
                                'label' => 'Environmental Screening Category',
                                'required' => true,
                                'options' => [
                                    'categorical_exclusion' => 'Categorical Exclusion',
                                    'initial_environmental_examination' => 'Initial Environmental Examination',
                                    'environmental_assessment' => 'Environmental Assessment'
                                ],
                                'help_text' => 'Select appropriate environmental screening category per USAID guidelines',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'environmental_assessment_document',
                                'field_type' => 'file_upload',
                                'label' => 'Environmental Assessment Document',
                                'required' => false,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '10MB'
                                ],
                                'conditional_logic' => [
                                    'show_if' => 'environmental_screening != categorical_exclusion'
                                ],
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'gender_integration',
                        'section_title' => 'Gender Integration',
                        'section_description' => 'Gender analysis and integration requirements',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'gender_analysis_completed',
                                'field_type' => 'checkbox',
                                'label' => 'Gender analysis has been completed',
                                'required' => true,
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'female_participation_percentage',
                                'field_type' => 'number',
                                'label' => 'Expected Female Participation (%)',
                                'required' => true,
                                'validation' => [
                                    'min' => 0,
                                    'max' => 100
                                ],
                                'help_text' => 'USAID typically requires minimum 30% female participation',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'gender_indicators',
                                'field_type' => 'textarea',
                                'label' => 'Gender-Sensitive Indicators',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 50
                                ],
                                'help_text' => 'Describe specific indicators that will measure gender integration',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'marking_branding',
                        'section_title' => 'Marking and Branding',
                        'section_description' => 'USAID marking and branding requirements',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'marking_branding_plan',
                                'field_type' => 'file_upload',
                                'label' => 'Marking and Branding Plan',
                                'required' => true,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '5MB'
                                ],
                                'help_text' => 'Upload detailed marking and branding plan per USAID requirements',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'usaid_logo_usage',
                                'field_type' => 'dropdown',
                                'label' => 'USAID Logo Usage',
                                'required' => true,
                                'options' => [
                                    'required' => 'Required (>$500K)',
                                    'permitted' => 'Permitted',
                                    'not_applicable' => 'Not Applicable'
                                ],
                                'grid_layout' => ['col' => 6]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $steps;
    }

    private function getUSAIDValidationRules(): array
    {
        return [
            [
                'rule_id' => 'usaid_budget_team_alignment',
                'rule_type' => 'business_logic',
                'conditions' => 'budget > 500000 AND team_size < 5',
                'error_message' => 'USAID projects over $500K should have at least 5 team members',
                'severity' => 'warning'
            ],
            [
                'rule_id' => 'usaid_gender_participation',
                'rule_type' => 'compliance',
                'conditions' => 'female_participation_percentage < 30',
                'error_message' => 'USAID typically requires minimum 30% female participation',
                'severity' => 'warning'
            ],
            [
                'rule_id' => 'usaid_environmental_budget_check',
                'rule_type' => 'cross_field',
                'conditions' => [
                    'budget > 500000 AND environmental_screening = categorical_exclusion'
                ],
                'error_message' => 'High-budget projects cannot use categorical exclusion',
                'severity' => 'error'
            ]
        ];
    }

    private function getUSAIDWorkflowConfiguration(): array
    {
        return [
            'approval_workflow' => [
                'steps' => [
                    [
                        'step_name' => 'technical_review',
                        'approver_role' => 'technical_officer',
                        'sla_days' => 3,
                        'required' => true
                    ],
                    [
                        'step_name' => 'agreement_officer_review',
                        'approver_role' => 'agreement_officer',
                        'sla_days' => 5,
                        'required' => true,
                        'conditions' => 'budget > 100000'
                    ],
                    [
                        'step_name' => 'contracting_officer_approval',
                        'approver_role' => 'contracting_officer',
                        'sla_days' => 7,
                        'required' => true,
                        'conditions' => 'budget > 500000'
                    ]
                ]
            ],
            'escalation_rules' => [
                [
                    'trigger' => 'sla_breach',
                    'escalate_to' => 'mission_director',
                    'notification_days' => 1
                ]
            ]
        ];
    }
}

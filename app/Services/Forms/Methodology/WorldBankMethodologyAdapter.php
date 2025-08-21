<?php

// File: app/Services/Forms/Methodology/WorldBankMethodologyAdapter.php
namespace App\Services\Forms\Methodology;

class WorldBankMethodologyAdapter implements MethodologyAdapterInterface
{
    public function adaptTemplate(array $templateData): array
    {
        // Add World Bank-specific sections and fields
        $templateData['steps'] = $this->addWorldBankRequiredSections($templateData['steps'] ?? []);
        
        // Add World Bank validation rules
        $templateData['validation_rules'] = array_merge(
            $templateData['validation_rules'] ?? [],
            $this->getWorldBankValidationRules()
        );
        
        // Add World Bank workflow configuration
        $templateData['workflow_configuration'] = array_merge(
            $templateData['workflow_configuration'] ?? [],
            $this->getWorldBankWorkflowConfiguration()
        );
        
        // Set compliance level
        $templateData['compliance_level'] = 'strict';
        
        return $templateData;
    }

    public function getRequirements(): array
    {
        return [
            'project_development_objective' => [
                'mandatory' => true,
                'description' => 'Clear Project Development Objective (PDO) statement required',
                'validation_rules' => ['min_length' => 50, 'max_length' => 500]
            ],
            'results_framework' => [
                'mandatory' => true,
                'description' => 'Comprehensive results framework with PDO and intermediate indicators',
                'components' => ['pdo_indicators', 'intermediate_indicators', 'activities', 'outputs', 'outcomes']
            ],
            'safeguards_screening' => [
                'mandatory' => true,
                'description' => 'Environmental and social safeguards screening required',
                'policies' => ['OP_4_01', 'OP_4_04', 'OP_4_10', 'OP_4_12'],
                'categories' => ['A', 'B', 'C']
            ],
            'procurement_plan' => [
                'mandatory' => true,
                'description' => 'Detailed procurement plan following World Bank guidelines',
                'trigger_conditions' => ['budget > 50000']
            ],
            'financial_management' => [
                'mandatory' => true,
                'description' => 'Financial management assessment and arrangements',
                'components' => ['budgeting', 'accounting', 'reporting', 'auditing']
            ]
        ];
    }

    public function getComplianceConfiguration(): array
    {
        return [
            'project_cycle_stages' => [
                'identification' => ['concept_note', 'pre_identification_mission'],
                'preparation' => ['preparation_mission', 'quality_enhancement_review'],
                'appraisal' => ['appraisal_mission', 'decision_meeting'],
                'negotiation' => ['legal_review', 'loan_agreement'],
                'implementation' => ['supervision_missions', 'mid_term_review'],
                'completion' => ['implementation_completion_report', 'project_completion_report']
            ],
            'quality_gates' => [
                'concept_review' => ['mandatory' => true, 'stage' => 'identification'],
                'decision_meeting' => ['mandatory' => true, 'stage' => 'preparation'],
                'qer' => ['mandatory' => true, 'stage' => 'preparation'],
                'board_approval' => ['mandatory' => true, 'stage' => 'appraisal']
            ],
            'approval_thresholds' => [
                'country_director' => 50000000,   // $50M
                'regional_director' => 100000000, // $100M
                'board_approval' => 200000000     // $200M
            ],
            'safeguards_policies' => [
                'OP_4_01' => 'Environmental Assessment',
                'OP_4_04' => 'Natural Habitats',
                'OP_4_09' => 'Pest Management',
                'OP_4_10' => 'Indigenous Peoples',
                'OP_4_11' => 'Physical Cultural Resources',
                'OP_4_12' => 'Involuntary Resettlement',
                'OP_4_36' => 'Forests',
                'OP_4_37' => 'Safety of Dams'
            ]
        ];
    }

    private function addWorldBankRequiredSections(array $steps): array
    {
        // Check if World Bank compliance step already exists
        $complianceStepExists = false;
        foreach ($steps as $step) {
            if (($step['step_id'] ?? '') === 'world_bank_compliance') {
                $complianceStepExists = true;
                break;
            }
        }

        if (!$complianceStepExists) {
            $steps[] = [
                'step_id' => 'world_bank_compliance',
                'step_number' => count($steps) + 1,
                'step_title' => 'World Bank Compliance Requirements',
                'step_description' => 'Complete World Bank-specific compliance and safeguards requirements',
                'icon' => 'pi pi-globe',
                'estimated_time' => '45 minutes',
                'is_required' => true,
                'methodology_specific' => true,
                'sections' => [
                    [
                        'section_id' => 'project_development_objective',
                        'section_title' => 'Project Development Objective (PDO)',
                        'section_description' => 'Define clear, measurable project development objective',
                        'collapsible' => false,
                        'methodology_adaptation' => true,
                        'fields' => [
                            [
                                'field_id' => 'project_development_objective',
                                'field_type' => 'textarea',
                                'label' => 'Project Development Objective Statement',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 50,
                                    'max_length' => 500
                                ],
                                'help_text' => 'Clear statement of what the project aims to achieve in terms of development outcomes',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'pdo_level',
                                'field_type' => 'dropdown',
                                'label' => 'PDO Level',
                                'required' => true,
                                'options' => [
                                    'outcome' => 'Outcome Level',
                                    'output' => 'Output Level',
                                    'impact' => 'Impact Level'
                                ],
                                'help_text' => 'Level at which the PDO is defined in the results chain',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'pdo_beneficiaries',
                                'field_type' => 'text',
                                'label' => 'Target Beneficiaries',
                                'required' => true,
                                'help_text' => 'Primary beneficiaries of the project',
                                'grid_layout' => ['col' => 6]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'results_framework',
                        'section_title' => 'Results Framework',
                        'section_description' => 'Comprehensive results framework with indicators and targets',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'pdo_indicators',
                                'field_type' => 'repeater',
                                'label' => 'PDO Indicators',
                                'required' => true,
                                'min_items' => 1,
                                'max_items' => 10,
                                'item_template' => [
                                    'indicator_name' => ['type' => 'text', 'label' => 'Indicator Name', 'required' => true],
                                    'baseline_value' => ['type' => 'number', 'label' => 'Baseline Value', 'required' => true],
                                    'target_value' => ['type' => 'number', 'label' => 'Target Value', 'required' => true],
                                    'unit_of_measure' => ['type' => 'text', 'label' => 'Unit of Measure', 'required' => true],
                                    'data_source' => ['type' => 'text', 'label' => 'Data Source', 'required' => true],
                                    'frequency' => ['type' => 'dropdown', 'label' => 'Frequency', 'options' => [
                                        'annual' => 'Annual',
                                        'semi_annual' => 'Semi-Annual',
                                        'quarterly' => 'Quarterly',
                                        'monthly' => 'Monthly'
                                    ]]
                                ],
                                'help_text' => 'Key indicators that measure achievement of the PDO',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'intermediate_indicators',
                                'field_type' => 'repeater',
                                'label' => 'Intermediate Results Indicators',
                                'required' => true,
                                'min_items' => 2,
                                'max_items' => 20,
                                'item_template' => [
                                    'component' => ['type' => 'text', 'label' => 'Component', 'required' => true],
                                    'indicator_name' => ['type' => 'text', 'label' => 'Indicator Name', 'required' => true],
                                    'baseline_value' => ['type' => 'number', 'label' => 'Baseline Value'],
                                    'target_value' => ['type' => 'number', 'label' => 'Target Value', 'required' => true],
                                    'unit_of_measure' => ['type' => 'text', 'label' => 'Unit of Measure', 'required' => true]
                                ],
                                'help_text' => 'Indicators that measure intermediate results leading to PDO achievement',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'results_chain_logic',
                                'field_type' => 'textarea',
                                'label' => 'Results Chain Logic',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'Explanation of how activities lead to outputs, outcomes, and PDO achievement',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'safeguards_screening',
                        'section_title' => 'Environmental and Social Safeguards',
                        'section_description' => 'Environmental and social safeguards screening and assessment',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'safeguards_category',
                                'field_type' => 'dropdown',
                                'label' => 'Environmental Safeguards Category',
                                'required' => true,
                                'options' => [
                                    'A' => 'Category A - Significant Environmental Impact',
                                    'B' => 'Category B - Limited Environmental Impact',
                                    'C' => 'Category C - Minimal Environmental Impact'
                                ],
                                'help_text' => 'Environmental impact category per OP 4.01',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'triggered_policies',
                                'field_type' => 'checkbox_group',
                                'label' => 'Triggered Safeguards Policies',
                                'required' => false,
                                'options' => [
                                    'OP_4_01' => 'OP 4.01 - Environmental Assessment',
                                    'OP_4_04' => 'OP 4.04 - Natural Habitats',
                                    'OP_4_09' => 'OP 4.09 - Pest Management',
                                    'OP_4_10' => 'OP 4.10 - Indigenous Peoples',
                                    'OP_4_11' => 'OP 4.11 - Physical Cultural Resources',
                                    'OP_4_12' => 'OP 4.12 - Involuntary Resettlement',
                                    'OP_4_36' => 'OP 4.36 - Forests',
                                    'OP_4_37' => 'OP 4.37 - Safety of Dams'
                                ],
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'environmental_assessment',
                                'field_type' => 'file_upload',
                                'label' => 'Environmental Assessment Document',
                                'required' => false,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '15MB'
                                ],
                                'conditional_logic' => [
                                    'show_if' => 'safeguards_category in [A,B]'
                                ],
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'resettlement_plan',
                                'field_type' => 'file_upload',
                                'label' => 'Resettlement Action Plan',
                                'required' => false,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '15MB'
                                ],
                                'conditional_logic' => [
                                    'show_if' => 'triggered_policies contains OP_4_12'
                                ],
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'indigenous_peoples_plan',
                                'field_type' => 'file_upload',
                                'label' => 'Indigenous Peoples Plan',
                                'required' => false,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '15MB'
                                ],
                                'conditional_logic' => [
                                    'show_if' => 'triggered_policies contains OP_4_10'
                                ],
                                'grid_layout' => ['col' => 6]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'procurement_arrangements',
                        'section_title' => 'Procurement Arrangements',
                        'section_description' => 'Procurement planning and arrangements per World Bank guidelines',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'procurement_plan',
                                'field_type' => 'file_upload',
                                'label' => 'Procurement Plan',
                                'required' => true,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'xls', 'xlsx'],
                                    'max_size' => '10MB'
                                ],
                                'help_text' => 'Detailed procurement plan following World Bank guidelines',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'procurement_methods',
                                'field_type' => 'checkbox_group',
                                'label' => 'Procurement Methods to be Used',
                                'required' => true,
                                'options' => [
                                    'icb' => 'International Competitive Bidding (ICB)',
                                    'ncb' => 'National Competitive Bidding (NCB)',
                                    'shopping' => 'Shopping',
                                    'direct_contracting' => 'Direct Contracting',
                                    'force_account' => 'Force Account',
                                    'community_participation' => 'Community Participation'
                                ],
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'prior_review_threshold',
                                'field_type' => 'currency',
                                'label' => 'Prior Review Threshold (USD)',
                                'required' => true,
                                'validation' => [
                                    'min' => 0,
                                    'max' => 10000000
                                ],
                                'help_text' => 'Threshold above which World Bank prior review is required',
                                'grid_layout' => ['col' => 6]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'financial_management',
                        'section_title' => 'Financial Management',
                        'section_description' => 'Financial management assessment and arrangements',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'fm_assessment_rating',
                                'field_type' => 'dropdown',
                                'label' => 'Financial Management Assessment Rating',
                                'required' => true,
                                'options' => [
                                    'satisfactory' => 'Satisfactory',
                                    'moderately_satisfactory' => 'Moderately Satisfactory',
                                    'moderately_unsatisfactory' => 'Moderately Unsatisfactory',
                                    'unsatisfactory' => 'Unsatisfactory'
                                ],
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'designated_account_amount',
                                'field_type' => 'currency',
                                'label' => 'Designated Account Amount (USD)',
                                'required' => true,
                                'validation' => [
                                    'min' => 0
                                ],
                                'help_text' => 'Amount to be maintained in the designated account',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'audit_arrangements',
                                'field_type' => 'textarea',
                                'label' => 'Audit Arrangements',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 50
                                ],
                                'help_text' => 'Description of audit arrangements and timeline',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'financial_reports_schedule',
                                'field_type' => 'dropdown',
                                'label' => 'Financial Reporting Schedule',
                                'required' => true,
                                'options' => [
                                    'monthly' => 'Monthly',
                                    'quarterly' => 'Quarterly',
                                    'semi_annual' => 'Semi-Annual',
                                    'annual' => 'Annual'
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

    private function getWorldBankValidationRules(): array
    {
        return [
            [
                'rule_id' => 'wb_pdo_indicators_minimum',
                'rule_type' => 'business_logic',
                'conditions' => 'count(pdo_indicators) < 1',
                'error_message' => 'World Bank projects must have at least one PDO indicator',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_intermediate_indicators_minimum',
                'rule_type' => 'business_logic',
                'conditions' => 'count(intermediate_indicators) < 2',
                'error_message' => 'World Bank projects should have at least two intermediate results indicators',
                'severity' => 'warning'
            ],
            [
                'rule_id' => 'wb_safeguards_category_assessment',
                'rule_type' => 'cross_field',
                'conditions' => [
                    'safeguards_category = A AND empty(environmental_assessment)'
                ],
                'error_message' => 'Category A projects must have an Environmental Assessment',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_resettlement_plan_required',
                'rule_type' => 'cross_field',
                'conditions' => [
                    'triggered_policies contains OP_4_12 AND empty(resettlement_plan)'
                ],
                'error_message' => 'Resettlement Action Plan required when OP 4.12 is triggered',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_indigenous_peoples_plan_required',
                'rule_type' => 'cross_field',
                'conditions' => [
                    'triggered_policies contains OP_4_10 AND empty(indigenous_peoples_plan)'
                ],
                'error_message' => 'Indigenous Peoples Plan required when OP 4.10 is triggered',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_procurement_plan_budget_alignment',
                'rule_type' => 'business_logic',
                'conditions' => 'budget > 1000000 AND empty(procurement_plan)',
                'error_message' => 'Projects over $1M must have a detailed procurement plan',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_fm_assessment_budget_threshold',
                'rule_type' => 'business_logic',
                'conditions' => 'budget > 500000 AND fm_assessment_rating in [moderately_unsatisfactory, unsatisfactory]',
                'error_message' => 'High-value projects require satisfactory FM assessment rating',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'wb_results_chain_logic_validation',
                'rule_type' => 'field',
                'field' => 'results_chain_logic',
                'conditions' => 'length < 100',
                'error_message' => 'Results chain logic must provide comprehensive explanation (minimum 100 characters)',
                'severity' => 'error'
            ]
        ];
    }

    private function getWorldBankWorkflowConfiguration(): array
    {
        return [
            'approval_workflow' => [
                'type' => 'sequential',
                'steps' => [
                    [
                        'step_name' => 'task_team_leader_review',
                        'approver_role' => 'task_team_leader',
                        'sla_days' => 5,
                        'required' => true,
                        'description' => 'Task Team Leader technical review'
                    ],
                    [
                        'step_name' => 'sector_manager_review',
                        'approver_role' => 'sector_manager',
                        'sla_days' => 7,
                        'required' => true,
                        'conditions' => 'budget > 10000000',
                        'description' => 'Sector Manager review for projects over $10M'
                    ],
                    [
                        'step_name' => 'country_director_approval',
                        'approver_role' => 'country_director',
                        'sla_days' => 10,
                        'required' => true,
                        'conditions' => 'budget > 50000000',
                        'description' => 'Country Director approval for projects over $50M'
                    ],
                    [
                        'step_name' => 'regional_director_approval',
                        'approver_role' => 'regional_director',
                        'sla_days' => 14,
                        'required' => true,
                        'conditions' => 'budget > 100000000',
                        'description' => 'Regional Director approval for projects over $100M'
                    ],
                    [
                        'step_name' => 'board_approval',
                        'approver_role' => 'board_member',
                        'sla_days' => 30,
                        'required' => true,
                        'conditions' => 'budget > 200000000',
                        'description' => 'World Bank Board approval for projects over $200M'
                    ]
                ]
            ],
            'quality_gates' => [
                [
                    'gate_name' => 'concept_review',
                    'stage' => 'identification',
                    'required_documents' => ['concept_note', 'initial_safeguards_screening'],
                    'mandatory' => true
                ],
                [
                    'gate_name' => 'decision_meeting',
                    'stage' => 'preparation',
                    'required_documents' => ['project_appraisal_document', 'safeguards_instruments'],
                    'mandatory' => true
                ],
                [
                    'gate_name' => 'quality_enhancement_review',
                    'stage' => 'preparation',
                    'required_documents' => ['results_framework', 'procurement_plan', 'fm_assessment'],
                    'mandatory' => true,
                    'conditions' => 'budget > 50000000'
                ]
            ],
            'escalation_rules' => [
                [
                    'trigger' => 'sla_breach',
                    'escalate_to' => 'sector_manager',
                    'notification_days' => 2
                ],
                [
                    'trigger' => 'safeguards_violation',
                    'escalate_to' => 'safeguards_specialist',
                    'notification_days' => 1
                ],
                [
                    'trigger' => 'budget_threshold_exceeded',
                    'escalate_to' => 'country_director',
                    'notification_days' => 1,
                    'conditions' => 'budget > 100000000'
                ]
            ],
            'mandatory_reviews' => [
                'environmental_safeguards' => [
                    'reviewer_role' => 'environmental_specialist',
                    'required_for_categories' => ['A', 'B'],
                    'sla_days' => 10
                ],
                'social_safeguards' => [
                    'reviewer_role' => 'social_development_specialist',
                    'required_for_policies' => ['OP_4_10', 'OP_4_12'],
                    'sla_days' => 10
                ],
                'financial_management' => [
                    'reviewer_role' => 'financial_management_specialist',
                    'required_for_all' => true,
                    'sla_days' => 7
                ],
                'procurement' => [
                    'reviewer_role' => 'procurement_specialist',
                    'required_for_all' => true,
                    'sla_days' => 7
                ]
            ]
        ];
    }
}
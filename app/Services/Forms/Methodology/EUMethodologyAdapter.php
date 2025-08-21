<?php

// File: app/Services/Forms/Methodology/EUMethodologyAdapter.php
namespace App\Services\Forms\Methodology;

class EUMethodologyAdapter implements MethodologyAdapterInterface
{
    public function adaptTemplate(array $templateData): array
    {
        // Add EU-specific sections and fields
        $templateData['steps'] = $this->addEURequiredSections($templateData['steps'] ?? []);
        
        // Add EU validation rules
        $templateData['validation_rules'] = array_merge(
            $templateData['validation_rules'] ?? [],
            $this->getEUValidationRules()
        );
        
        // Add EU workflow configuration
        $templateData['workflow_configuration'] = array_merge(
            $templateData['workflow_configuration'] ?? [],
            $this->getEUWorkflowConfiguration()
        );
        
        // Set compliance level
        $templateData['compliance_level'] = 'strict';
        
        return $templateData;
    }

    public function getRequirements(): array
    {
        return [
            'logical_framework' => [
                'mandatory' => true,
                'description' => 'Comprehensive logical framework (LogFrame) with intervention logic',
                'components' => ['overall_objective', 'specific_objectives', 'expected_results', 'activities', 'indicators', 'assumptions']
            ],
            'sustainability_plan' => [
                'mandatory' => true,
                'description' => 'Detailed sustainability strategy ensuring project continuity',
                'aspects' => ['financial_sustainability', 'institutional_sustainability', 'environmental_sustainability', 'social_sustainability']
            ],
            'visibility_plan' => [
                'mandatory' => true,
                'description' => 'EU visibility and communication plan as per EU guidelines',
                'requirements' => ['eu_flag', 'communication_activities', 'target_audiences']
            ],
            'gender_equality' => [
                'mandatory' => true,
                'description' => 'Gender equality integration and analysis',
                'scoring' => ['0' => 'Gender blind', '1' => 'Gender sensitive', '2' => 'Gender transformative']
            ],
            'human_rights_approach' => [
                'mandatory' => true,
                'description' => 'Human rights-based approach analysis and integration',
                'components' => ['rights_holders', 'duty_bearers', 'accountability_mechanisms']
            ],
            'cross_cutting_themes' => [
                'mandatory' => true,
                'description' => 'Integration of EU cross-cutting themes',
                'themes' => ['gender_equality', 'environment_climate', 'governance_human_rights', 'social_inclusion']
            ]
        ];
    }

    public function getComplianceConfiguration(): array
    {
        return [
            'project_cycle_management' => [
                'identification' => ['needs_assessment', 'stakeholder_analysis'],
                'formulation' => ['logical_framework', 'feasibility_study'],
                'implementation' => ['monitoring_system', 'regular_reporting'],
                'evaluation' => ['mid_term_evaluation', 'final_evaluation']
            ],
            'cross_cutting_themes_scoring' => [
                'gender_equality' => ['0', '1', '2'],
                'environment_climate' => ['0', '1', '2'],
                'governance_human_rights' => ['0', '1', '2'],
                'social_inclusion' => ['0', '1', '2']
            ],
            'minimum_requirements' => [
                'gender_equality_score' => 1,
                'environment_climate_score' => 1,
                'governance_human_rights_score' => 1,
                'logframe_completeness' => 100,
                'sustainability_coverage' => 80
            ],
            'visibility_requirements' => [
                'mandatory_elements' => ['eu_flag', 'eu_funding_statement'],
                'communication_channels' => ['website', 'social_media', 'printed_materials', 'events'],
                'reporting_frequency' => 'quarterly'
            ],
            'result_measurement' => [
                'outcome_indicators' => ['mandatory' => true, 'minimum' => 2],
                'output_indicators' => ['mandatory' => true, 'minimum' => 3],
                'impact_indicators' => ['mandatory' => false, 'recommended' => true]
            ]
        ];
    }

    private function addEURequiredSections(array $steps): array
    {
        // Check if EU compliance step already exists
        $complianceStepExists = false;
        foreach ($steps as $step) {
            if (($step['step_id'] ?? '') === 'eu_compliance') {
                $complianceStepExists = true;
                break;
            }
        }

        if (!$complianceStepExists) {
            $steps[] = [
                'step_id' => 'eu_compliance',
                'step_number' => count($steps) + 1,
                'step_title' => 'EU Compliance Requirements',
                'step_description' => 'Complete EU-specific compliance requirements and cross-cutting themes',
                'icon' => 'pi pi-star',
                'estimated_time' => '60 minutes',
                'is_required' => true,
                'methodology_specific' => true,
                'sections' => [
                    [
                        'section_id' => 'logical_framework',
                        'section_title' => 'Logical Framework (LogFrame)',
                        'section_description' => 'Comprehensive logical framework with intervention logic',
                        'collapsible' => false,
                        'methodology_adaptation' => true,
                        'fields' => [
                            [
                                'field_id' => 'overall_objective',
                                'field_type' => 'textarea',
                                'label' => 'Overall Objective',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 50,
                                    'max_length' => 300
                                ],
                                'help_text' => 'The broader development goal to which the project contributes',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'specific_objectives',
                                'field_type' => 'repeater',
                                'label' => 'Specific Objectives',
                                'required' => true,
                                'min_items' => 1,
                                'max_items' => 5,
                                'item_template' => [
                                    'objective_statement' => ['type' => 'textarea', 'label' => 'Objective Statement', 'required' => true],
                                    'indicators' => ['type' => 'repeater', 'label' => 'Indicators', 'min_items' => 1, 'item_template' => [
                                        'indicator_name' => ['type' => 'text', 'label' => 'Indicator', 'required' => true],
                                        'baseline' => ['type' => 'text', 'label' => 'Baseline', 'required' => true],
                                        'target' => ['type' => 'text', 'label' => 'Target', 'required' => true],
                                        'source_of_verification' => ['type' => 'text', 'label' => 'Source of Verification', 'required' => true]
                                    ]],
                                    'assumptions' => ['type' => 'textarea', 'label' => 'Assumptions', 'required' => false]
                                ],
                                'help_text' => 'Specific objectives that the project will achieve',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'expected_results',
                                'field_type' => 'repeater',
                                'label' => 'Expected Results (Outcomes)',
                                'required' => true,
                                'min_items' => 2,
                                'max_items' => 10,
                                'item_template' => [
                                    'result_statement' => ['type' => 'textarea', 'label' => 'Result Statement', 'required' => true],
                                    'indicators' => ['type' => 'repeater', 'label' => 'Indicators', 'min_items' => 1, 'item_template' => [
                                        'indicator_name' => ['type' => 'text', 'label' => 'Indicator', 'required' => true],
                                        'baseline' => ['type' => 'text', 'label' => 'Baseline', 'required' => true],
                                        'target' => ['type' => 'text', 'label' => 'Target', 'required' => true],
                                        'source_of_verification' => ['type' => 'text', 'label' => 'Source of Verification', 'required' => true]
                                    ]],
                                    'assumptions' => ['type' => 'textarea', 'label' => 'Assumptions', 'required' => false]
                                ],
                                'help_text' => 'Expected results/outcomes from project implementation',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'activities',
                                'field_type' => 'repeater',
                                'label' => 'Activities',
                                'required' => true,
                                'min_items' => 3,
                                'max_items' => 20,
                                'item_template' => [
                                    'activity_description' => ['type' => 'textarea', 'label' => 'Activity Description', 'required' => true],
                                    'outputs' => ['type' => 'repeater', 'label' => 'Outputs', 'min_items' => 1, 'item_template' => [
                                        'output_description' => ['type' => 'text', 'label' => 'Output', 'required' => true],
                                        'indicators' => ['type' => 'text', 'label' => 'Indicators', 'required' => true],
                                        'target' => ['type' => 'text', 'label' => 'Target', 'required' => true]
                                    ]],
                                    'means_of_implementation' => ['type' => 'text', 'label' => 'Means of Implementation', 'required' => true],
                                    'assumptions' => ['type' => 'textarea', 'label' => 'Assumptions', 'required' => false]
                                ],
                                'help_text' => 'Key activities to be implemented',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'cross_cutting_themes',
                        'section_title' => 'Cross-Cutting Themes',
                        'section_description' => 'EU cross-cutting themes integration and scoring',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'gender_integration_score',
                                'field_type' => 'dropdown',
                                'label' => 'Gender Equality Integration Score',
                                'required' => true,
                                'options' => [
                                    '0' => 'Score 0 - Gender Blind (not relevant)',
                                    '1' => 'Score 1 - Gender Sensitive (some consideration)',
                                    '2' => 'Score 2 - Gender Transformative (primary focus)'
                                ],
                                'help_text' => 'EU requires minimum score of 1 for gender integration',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'environmental_sustainability_score',
                                'field_type' => 'dropdown',
                                'label' => 'Environment & Climate Change Score',
                                'required' => true,
                                'options' => [
                                    '0' => 'Score 0 - Not relevant',
                                    '1' => 'Score 1 - Some environmental consideration',
                                    '2' => 'Score 2 - Primary environmental focus'
                                ],
                                'help_text' => 'Assessment of environmental and climate change integration',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'governance_human_rights_score',
                                'field_type' => 'dropdown',
                                'label' => 'Governance & Human Rights Score',
                                'required' => true,
                                'options' => [
                                    '0' => 'Score 0 - Not relevant',
                                    '1' => 'Score 1 - Some governance/HR consideration',
                                    '2' => 'Score 2 - Primary governance/HR focus'
                                ],
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'social_inclusion_score',
                                'field_type' => 'dropdown',
                                'label' => 'Social Inclusion Score',
                                'required' => true,
                                'options' => [
                                    '0' => 'Score 0 - Not relevant',
                                    '1' => 'Score 1 - Some social inclusion consideration',
                                    '2' => 'Score 2 - Primary social inclusion focus'
                                ],
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'cross_cutting_analysis',
                                'field_type' => 'textarea',
                                'label' => 'Cross-Cutting Themes Analysis',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 200
                                ],
                                'help_text' => 'Detailed analysis of how cross-cutting themes are integrated',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'human_rights_analysis',
                        'section_title' => 'Human Rights-Based Approach',
                        'section_description' => 'Human rights analysis and approach integration',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'rights_holders_analysis',
                                'field_type' => 'textarea',
                                'label' => 'Rights Holders Analysis',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'Identify and analyze the rights holders affected by the project',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'duty_bearers_analysis',
                                'field_type' => 'textarea',
                                'label' => 'Duty Bearers Analysis',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'Identify and analyze the duty bearers responsible for fulfilling rights',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'accountability_mechanisms',
                                'field_type' => 'textarea',
                                'label' => 'Accountability Mechanisms',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'Describe accountability mechanisms and grievance procedures',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'sustainability_strategy',
                        'section_title' => 'Sustainability Strategy',
                        'section_description' => 'Comprehensive sustainability planning',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'financial_sustainability',
                                'field_type' => 'textarea',
                                'label' => 'Financial Sustainability',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'How will the project results be financially sustained?',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'institutional_sustainability',
                                'field_type' => 'textarea',
                                'label' => 'Institutional Sustainability',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'How will institutional capacity be maintained?',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'environmental_sustainability',
                                'field_type' => 'textarea',
                                'label' => 'Environmental Sustainability',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'How will environmental impacts be managed long-term?',
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'social_sustainability',
                                'field_type' => 'textarea',
                                'label' => 'Social Sustainability',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 100
                                ],
                                'help_text' => 'How will social benefits be sustained?',
                                'grid_layout' => ['col' => 6]
                            ]
                        ]
                    ],
                    [
                        'section_id' => 'visibility_communication',
                        'section_title' => 'Visibility and Communication',
                        'section_description' => 'EU visibility requirements and communication plan',
                        'collapsible' => false,
                        'fields' => [
                            [
                                'field_id' => 'visibility_plan',
                                'field_type' => 'file_upload',
                                'label' => 'EU Visibility and Communication Plan',
                                'required' => true,
                                'validation' => [
                                    'allowed_types' => ['pdf', 'doc', 'docx'],
                                    'max_size' => '10MB'
                                ],
                                'help_text' => 'Detailed plan following EU visibility guidelines',
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'eu_flag_display',
                                'field_type' => 'checkbox',
                                'label' => 'EU flag will be displayed prominently',
                                'required' => true,
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'eu_funding_acknowledgment',
                                'field_type' => 'checkbox',
                                'label' => 'EU funding will be acknowledged in all materials',
                                'required' => true,
                                'grid_layout' => ['col' => 6]
                            ],
                            [
                                'field_id' => 'communication_activities',
                                'field_type' => 'checkbox_group',
                                'label' => 'Planned Communication Activities',
                                'required' => true,
                                'options' => [
                                    'website' => 'Project Website',
                                    'social_media' => 'Social Media Campaigns',
                                    'printed_materials' => 'Printed Materials (brochures, posters)',
                                    'media_events' => 'Media Events/Press Releases',
                                    'public_events' => 'Public Events/Conferences',
                                    'newsletters' => 'Regular Newsletters',
                                    'documentary' => 'Documentary/Video Content'
                                ],
                                'grid_layout' => ['col' => 12]
                            ],
                            [
                                'field_id' => 'target_audiences',
                                'field_type' => 'textarea',
                                'label' => 'Target Audiences',
                                'required' => true,
                                'validation' => [
                                    'min_length' => 50
                                ],
                                'help_text' => 'Define primary and secondary target audiences for communication',
                                'grid_layout' => ['col' => 12]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $steps;
    }

    private function getEUValidationRules(): array
    {
        return [
            [
                'rule_id' => 'eu_gender_minimum_score',
                'rule_type' => 'compliance',
                'conditions' => 'gender_integration_score < 1',
                'error_message' => 'EU requires minimum gender integration score of 1',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_logframe_completeness',
                'rule_type' => 'business_logic',
                'conditions' => 'empty(overall_objective) OR empty(specific_objectives) OR empty(expected_results) OR empty(activities)',
                'error_message' => 'Logical framework must include all levels: overall objective, specific objectives, expected results, and activities',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_cross_cutting_minimum',
                'rule_type' => 'business_logic',
                'conditions' => 'gender_integration_score = 0 AND environmental_sustainability_score = 0 AND governance_human_rights_score = 0 AND social_inclusion_score = 0',
                'error_message' => 'At least one cross-cutting theme must have a score of 1 or higher',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_sustainability_coverage',
                'rule_type' => 'business_logic',
                'conditions' => 'empty(financial_sustainability) OR empty(institutional_sustainability) OR empty(environmental_sustainability) OR empty(social_sustainability)',
                'error_message' => 'All four sustainability dimensions must be addressed',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_human_rights_analysis',
                'rule_type' => 'business_logic',
                'conditions' => 'empty(rights_holders_analysis) OR empty(duty_bearers_analysis) OR empty(accountability_mechanisms)',
                'error_message' => 'Human rights-based approach requires analysis of rights holders, duty bearers, and accountability mechanisms',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_visibility_requirements',
                'rule_type' => 'business_logic',
                'conditions' => 'eu_flag_display = false OR eu_funding_acknowledgment = false OR empty(visibility_plan)',
                'error_message' => 'EU visibility requirements must be fully addressed',
                'severity' => 'error'
            ],
            [
                'rule_id' => 'eu_indicator_minimum',
                'rule_type' => 'business_logic',
                'conditions' => 'count(specific_objectives.indicators) < 2 OR count(expected_results.indicators) < 3',
                'error_message' => 'Minimum 2 indicators per specific objective and 3 indicators for expected results',
                'severity' => 'warning'
            ],
            [
                'rule_id' => 'eu_communication_activities',
                'rule_type' => 'business_logic',
                'conditions' => 'count(communication_activities) < 2',
                'error_message' => 'At least 2 communication activities should be planned',
                'severity' => 'warning'
            ]
        ];
    }

    private function getEUWorkflowConfiguration(): array
    {
        return [
            'approval_workflow' => [
                'type' => 'sequential',
                'steps' => [
                    [
                        'step_name' => 'project_manager_review',
                        'approver_role' => 'project_manager',
                        'sla_days' => 5,
                        'required' => true,
                        'description' => 'Project Manager technical review'
                    ],
                    [
                        'step_name' => 'sector_specialist_review',
                        'approver_role' => 'sector_specialist',
                        'sla_days' => 7,
                        'required' => true,
                        'description' => 'Sector specialist technical review'
                    ],
                    [
                        'step_name' => 'cross_cutting_themes_review',
                        'approver_role' => 'cross_cutting_specialist',
                        'sla_days' => 5,
                        'required' => true,
                        'conditions' => 'gender_integration_score > 0 OR environmental_sustainability_score > 0',
                        'description' => 'Cross-cutting themes specialist review'
                    ],
                    [
                        'step_name' => 'programme_manager_approval',
                        'approver_role' => 'programme_manager',
                        'sla_days' => 10,
                        'required' => true,
                        'conditions' => 'budget > 1000000',
                        'description' => 'Programme Manager approval for projects over €1M'
                    ],
                    [
                        'step_name' => 'head_of_cooperation_approval',
                        'approver_role' => 'head_of_cooperation',
                        'sla_days' => 14,
                        'required' => true,
                        'conditions' => 'budget > 5000000',
                        'description' => 'Head of Cooperation approval for projects over €5M'
                    ],
                    [
                        'step_name' => 'headquarters_approval',
                        'approver_role' => 'headquarters_desk_officer',
                        'sla_days' => 21,
                        'required' => true,
                        'conditions' => 'budget > 10000000',
                        'description' => 'Headquarters approval for projects over €10M'
                    ]
                ]
            ],
            'quality_assurance' => [
                [
                    'check_name' => 'logframe_quality',
                    'reviewer_role' => 'results_specialist',
                    'mandatory' => true,
                    'criteria' => ['intervention_logic', 'indicator_quality', 'assumption_validity']
                ],
                [
                    'check_name' => 'cross_cutting_integration',
                    'reviewer_role' => 'cross_cutting_specialist',
                    'mandatory' => true,
                    'criteria' => ['gender_mainstreaming', 'environmental_integration', 'human_rights_approach']
                ],
                [
                    'check_name' => 'sustainability_assessment',
                    'reviewer_role' => 'sustainability_specialist',
                    'mandatory' => true,
                    'criteria' => ['financial_viability', 'institutional_capacity', 'environmental_impact']
                ]
            ],
            'escalation_rules' => [
                [
                    'trigger' => 'cross_cutting_score_low',
                    'escalate_to' => 'cross_cutting_specialist',
                    'notification_days' => 1,
                    'conditions' => 'all_cross_cutting_scores < 1'
                ],
                [
                    'trigger' => 'sustainability_insufficient',
                    'escalate_to' => 'programme_manager',
                    'notification_days' => 2,
                    'conditions' => 'sustainability_analysis_incomplete'
                ],
                [
                    'trigger' => 'visibility_non_compliance',
                    'escalate_to' => 'communication_officer',
                    'notification_days' => 1,
                    'conditions' => 'visibility_requirements_not_met'
                ]
            ],
            'mandatory_consultations' => [
                'cross_cutting_themes' => [
                    'consultant_role' => 'cross_cutting_specialist',
                    'required_for_scores' => ['1', '2'],
                    'sla_days' => 5
                ],
                'human_rights' => [
                    'consultant_role' => 'human_rights_specialist',
                    'required_for_all' => true,
                    'sla_days' => 7
                ],
                'visibility_communication' => [
                    'consultant_role' => 'communication_officer',
                    'required_for_all' => true,
                    'sla_days' => 3
                ]
            ]
        ];
    }
}
<?php

namespace Database\Factories\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormTemplateFactory extends Factory
{
    protected $model = FormTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'version' => '1.0',
            'category' => $this->faker->randomElement([
                'project_creation',
                'contract_management',
                'procurement',
                'monitoring',
                'financial',
                'custom'
            ]),
            'estimated_completion_time' => $this->faker->randomElement([
                '15 minutes',
                '30 minutes',
                '1 hour',
                '2 hours'
            ]),
            'is_multi_step' => $this->faker->boolean(),
            'auto_save' => $this->faker->boolean(80), // 80% chance of being true
            'compliance_level' => $this->faker->randomElement([
                'basic',
                'standard',
                'advanced',
                'comprehensive'
            ]),
            'is_active' => $this->faker->boolean(90), // 90% chance of being true
            'is_default' => false,
            'metadata' => [
                'tags' => $this->faker->words(3),
                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
                'department' => $this->faker->department(),
                'stakeholders' => []
            ],
            'form_configuration' => [
                'title' => $this->faker->sentence(3),
                'auto_save' => $this->faker->boolean(80),
                'allow_draft' => true,
                'description' => $this->faker->paragraph(),
                'requires_approval' => $this->faker->boolean(30),
                'estimated_duration' => $this->faker->numberBetween(15, 120)
            ],
            'steps' => [
                [
                    'step_id' => 'main',
                    'step_number' => 1,
                    'step_title' => 'Main Form',
                    'step_description' => 'Primary form fields',
                    'sections' => [
                        [
                            'section_id' => 'main_section',
                            'section_title' => 'Form Fields',
                            'fields' => [
                                [
                                    'field_id' => 'name',
                                    'field_type' => 'text',
                                    'label' => 'Name',
                                    'placeholder' => 'Enter your name',
                                    'description' => 'Full name of the person',
                                    'help_text' => 'Please enter your full name as it appears on official documents',
                                    'required' => true,
                                    'order' => 0,
                                    'group' => null,
                                    'depends_on' => null,
                                    'show_when' => null,
                                    'options' => [],
                                    'validation_rules' => [
                                        [
                                            'type' => 'required',
                                            'value' => null,
                                            'message' => 'Name is required'
                                        ],
                                        [
                                            'type' => 'min_length',
                                            'value' => 2,
                                            'message' => 'Name must be at least 2 characters'
                                        ]
                                    ],
                                    'conditional_logic' => [],
                                    'properties' => [
                                        'rows' => 1,
                                        'step' => 1,
                                        'accept' => null,
                                        'format' => null,
                                        'pattern' => null,
                                        'toolbar' => [],
                                        'max_date' => null,
                                        'max_size' => null,
                                        'min_date' => null,
                                        'multiple' => false,
                                        'clearable' => false,
                                        'max_files' => null,
                                        'max_items' => null,
                                        'max_value' => null,
                                        'min_items' => null,
                                        'min_value' => null,
                                        'max_length' => 100,
                                        'max_rating' => null,
                                        'min_length' => 2,
                                        'searchable' => false,
                                        'reorderable' => false,
                                        'currency_code' => null,
                                        'decimal_places' => null
                                    ]
                                ],
                                [
                                    'field_id' => 'email',
                                    'field_type' => 'email',
                                    'label' => 'Email Address',
                                    'placeholder' => 'Enter your email',
                                    'description' => 'Contact email address',
                                    'help_text' => 'We will use this email for communication',
                                    'required' => true,
                                    'order' => 1,
                                    'group' => null,
                                    'depends_on' => null,
                                    'show_when' => null,
                                    'options' => [],
                                    'validation_rules' => [
                                        [
                                            'type' => 'required',
                                            'value' => null,
                                            'message' => 'Email is required'
                                        ],
                                        [
                                            'type' => 'email',
                                            'value' => null,
                                            'message' => 'Please enter a valid email address'
                                        ]
                                    ],
                                    'conditional_logic' => [],
                                    'properties' => [
                                        'rows' => 1,
                                        'step' => 1,
                                        'accept' => null,
                                        'format' => null,
                                        'pattern' => null,
                                        'toolbar' => [],
                                        'max_date' => null,
                                        'max_size' => null,
                                        'min_date' => null,
                                        'multiple' => false,
                                        'clearable' => false,
                                        'max_files' => null,
                                        'max_items' => null,
                                        'max_value' => null,
                                        'min_items' => null,
                                        'min_value' => null,
                                        'max_length' => 255,
                                        'max_rating' => null,
                                        'min_length' => null,
                                        'searchable' => false,
                                        'reorderable' => false,
                                        'currency_code' => null,
                                        'decimal_places' => null
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'validation_rules' => [],
            'workflow_configuration' => [
                'auto_approval' => false,
                'approval_steps' => [],
                'escalation_rules' => []
            ],
            'ai_prompts' => null,
            'form_triggers' => null,
            'created_by' => User::factory()
        ];
    }

    /**
     * Indicate that the template is multi-step
     */
    public function multiStep(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_multi_step' => true,
            'steps' => [
                [
                    'step_id' => 'step_1',
                    'step_number' => 1,
                    'step_title' => 'Personal Information',
                    'step_description' => 'Basic personal details',
                    'sections' => [
                        [
                            'section_id' => 'personal_section',
                            'section_title' => 'Personal Details',
                            'fields' => [
                                [
                                    'field_id' => 'name',
                                    'field_type' => 'text',
                                    'label' => 'Full Name',
                                    'required' => true,
                                    'properties' => ['max_length' => 100]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'step_id' => 'step_2',
                    'step_number' => 2,
                    'step_title' => 'Contact Information',
                    'step_description' => 'Contact details',
                    'sections' => [
                        [
                            'section_id' => 'contact_section',
                            'section_title' => 'Contact Details',
                            'fields' => [
                                [
                                    'field_id' => 'email',
                                    'field_type' => 'email',
                                    'label' => 'Email Address',
                                    'required' => true,
                                    'properties' => ['max_length' => 255]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Indicate that the template has conditional logic
     */
    public function withConditionalLogic(): static
    {
        return $this->state(fn (array $attributes) => [
            'steps' => [
                [
                    'step_id' => 'main',
                    'step_number' => 1,
                    'step_title' => 'Main Form',
                    'step_description' => 'Form with conditional logic',
                    'sections' => [
                        [
                            'section_id' => 'main_section',
                            'section_title' => 'Form Fields',
                            'fields' => [
                                [
                                    'field_id' => 'has_children',
                                    'field_type' => 'checkbox',
                                    'label' => 'Do you have children?',
                                    'required' => false,
                                    'properties' => []
                                ],
                                [
                                    'field_id' => 'children_count',
                                    'field_type' => 'number',
                                    'label' => 'Number of children',
                                    'required' => false,
                                    'conditional_logic' => [
                                        [
                                            'field' => 'has_children',
                                            'operator' => 'equals',
                                            'value' => true,
                                            'action' => 'show'
                                        ]
                                    ],
                                    'properties' => ['min_value' => 0, 'max_value' => 20]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }
}

<?php 
// File: config/form_engine.php
return [
    /*
    |--------------------------------------------------------------------------
    | Form Engine Configuration
    |--------------------------------------------------------------------------
    */

    // Default form settings
    'defaults' => [
        'auto_save_interval' => 30, // seconds
        'max_file_size' => '10MB',
        'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
        'form_timeout' => 3600, // 1 hour
        'max_form_data_size' => '50MB',
    ],

    // Intelligence settings
    'intelligence' => [
        'enable_suggestions' => true,
        'suggestion_cache_ttl' => 300, // 5 minutes
        'enable_auto_population' => true,
        'enable_smart_validation' => true,
        'max_suggestions_per_field' => 10,
        'enable_ai_assistance' => env('FORM_AI_ENABLED', false),
        'ai_confidence_threshold' => 0.7,
    ],

    // Validation settings
    'validation' => [
        'enable_real_time_validation' => true,
        'enable_compliance_checking' => true,
        'max_validation_errors' => 50,
        'validation_cache_ttl' => 600, // 10 minutes
        'strict_compliance_mode' => env('FORM_STRICT_COMPLIANCE', false),
    ],

    // Workflow settings
    'workflow' => [
        'enable_workflows' => true,
        'default_sla_hours' => 72,
        'enable_escalations' => true,
        'max_escalation_levels' => 3,
        'notification_delays' => [
            'initial' => 0,
            'reminder' => 24, // hours
            'escalation' => 72, // hours
        ],
    ],

    // Methodology adapters
    'methodologies' => [
        'usaid' => [
            'enabled' => true,
            'adapter_class' => \App\Services\Forms\Methodology\USAIDMethodologyAdapter::class,
            'strict_compliance' => true,
            'required_fields' => ['environmental_screening', 'gender_integration', 'marking_branding_plan'],
        ],
        'world_bank' => [
            'enabled' => true,
            'adapter_class' => \App\Services\Forms\Methodology\WorldBankMethodologyAdapter::class,
            'strict_compliance' => true,
            'required_fields' => ['project_development_objective', 'results_framework', 'safeguards_screening'],
        ],
        'eu' => [
            'enabled' => true,
            'adapter_class' => \App\Services\Forms\Methodology\EUMethodologyAdapter::class,
            'strict_compliance' => true,
            'required_fields' => ['logical_framework', 'sustainability_plan', 'visibility_plan'],
        ],
    ],

    // Field types configuration
    'field_types' => [
        'text' => ['max_length' => 255, 'min_length' => 0],
        'textarea' => ['max_length' => 5000, 'min_length' => 0],
        'number' => ['max_value' => 999999999, 'min_value' => -999999999],
        'currency' => ['max_value' => 999999999, 'min_value' => 0],
        'file_upload' => [
            'max_files' => 10,
            'max_size' => '10MB',
            'allowed_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']
        ],
        'image_upload' => [
            'max_files' => 5,
            'max_size' => '5MB',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
        ],
    ],

    // Security settings
    'security' => [
        'enable_field_encryption' => false,
        'encrypted_field_types' => ['password', 'ssn', 'personal_id'],
        'enable_audit_logging' => true,
        'enable_data_anonymization' => false,
        'session_timeout' => 3600, // 1 hour
    ],

    // Performance settings
    'performance' => [
        'enable_caching' => true,
        'cache_ttl' => 3600,
        'enable_lazy_loading' => true,
        'max_concurrent_forms' => 50,
        'enable_compression' => true,
    ],

    // Integration settings
    'integrations' => [
        'enable_webhook_notifications' => false,
        'webhook_timeout' => 30, // seconds
        'enable_api_callbacks' => false,
        'max_retry_attempts' => 3,
    ],
];

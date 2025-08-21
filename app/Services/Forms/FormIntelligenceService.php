<?php

// =====================================================
// FORM INTELLIGENCE SERVICES
// =====================================================

// File: app/Services/Forms/FormIntelligenceService.php
namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use App\Services\AI\AIServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FormIntelligenceService
{
    protected $aiService;
    protected $patternEngine;
    protected $ruleEngine;

    public function __construct(
        ?AIServiceInterface $aiService = null,
        FormPatternEngine $patternEngine,
        FormRuleEngine $ruleEngine
    ) {
        $this->aiService = $aiService;
        $this->patternEngine = $patternEngine;
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Generate intelligent field suggestions based on context
     */
    public function generateFieldSuggestions(string $fieldId, array $context): array
    {
        $cacheKey = "field_suggestions_{$fieldId}_" . md5(json_encode($context));
        
        return Cache::remember($cacheKey, 300, function () use ($fieldId, $context) {
            $suggestions = [];
            
            // Rule-based suggestions (always available)
            $ruleSuggestions = $this->ruleEngine->generateSuggestions($fieldId, $context);
            $suggestions = array_merge($suggestions, $ruleSuggestions);
            
            // Pattern-based suggestions
            $patternSuggestions = $this->patternEngine->generateSuggestions($fieldId, $context);
            $suggestions = array_merge($suggestions, $patternSuggestions);
            
            // AI-enhanced suggestions (if available)
            if ($this->aiService && $this->aiService->isAvailable()) {
                try {
                    $aiSuggestions = $this->aiService->generateFieldSuggestions($fieldId, $context);
                    $suggestions = array_merge($suggestions, $aiSuggestions);
                } catch (\Exception $e) {
                    Log::warning('AI service failed for field suggestions', [
                        'field_id' => $fieldId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Remove duplicates and limit results
            return array_unique(array_slice($suggestions, 0, 10));
        });
    }

    /**
     * Validate form data with intelligent checks
     */
    public function validateFormData(array $formData, FormTemplate $template): array
    {
        $validator = new SmartFormValidator($template, $this->ruleEngine);
        
        // Rule-based validation
        $ruleValidation = $validator->validateRules($formData);
        
        // Compliance validation
        $complianceValidation = $validator->validateCompliance($formData, $template->methodology_type);
        
        // Business logic validation
        $businessValidation = $validator->validateBusinessLogic($formData);
        
        // Cross-field validation
        $crossFieldValidation = $validator->validateCrossFields($formData);
        
        // AI-enhanced validation (if available)
        $aiValidation = ['valid' => true, 'errors' => [], 'warnings' => []];
        if ($this->aiService && $this->aiService->isAvailable()) {
            try {
                $aiValidation = $this->aiService->validateFormData($formData, $template);
            } catch (\Exception $e) {
                Log::warning('AI validation failed', ['error' => $e->getMessage()]);
            }
        }
        
        return [
            'valid' => $ruleValidation['valid'] && 
                      $complianceValidation['valid'] && 
                      $businessValidation['valid'] && 
                      $crossFieldValidation['valid'] && 
                      $aiValidation['valid'],
            'errors' => array_merge(
                $ruleValidation['errors'] ?? [],
                $complianceValidation['errors'] ?? [],
                $businessValidation['errors'] ?? [],
                $crossFieldValidation['errors'] ?? [],
                $aiValidation['errors'] ?? []
            ),
            'warnings' => array_merge(
                $ruleValidation['warnings'] ?? [],
                $complianceValidation['warnings'] ?? [],
                $businessValidation['warnings'] ?? [],
                $crossFieldValidation['warnings'] ?? [],
                $aiValidation['warnings'] ?? []
            ),
            'details' => [
                'rule_validation' => $ruleValidation,
                'compliance_validation' => $complianceValidation,
                'business_validation' => $businessValidation,
                'cross_field_validation' => $crossFieldValidation,
                'ai_validation' => $aiValidation
            ]
        ];
    }

    /**
     * Calculate derived/computed fields
     */
    public function calculateDerivedFields(array $formData, FormTemplate $template): array
    {
        $calculatedFields = [];
        $fields = $template->getAllFields();
        
        foreach ($fields as $fieldId => $field) {
            if (($field['field_type'] ?? '') === 'calculated_field') {
                $formula = $field['calculation_formula'] ?? '';
                if ($formula) {
                    try {
                        $value = $this->ruleEngine->calculateField($formula, $formData);
                        $calculatedFields[$fieldId] = $value;
                    } catch (\Exception $e) {
                        Log::warning('Field calculation failed', [
                            'field_id' => $fieldId,
                            'formula' => $formula,
                            'error' => $e->getMessage()
                        ]);
                        $calculatedFields[$fieldId] = null;
                    }
                }
            }
        }
        
        return $calculatedFields;
    }

    /**
     * Auto-populate fields based on context
     */
    public function autoPopulateFields(FormTemplate $template, array $context): array
    {
        $populatedData = [];
        
        // Pattern-based auto-population
        $patternData = $this->patternEngine->autoPopulateFields($template, $context);
        $populatedData = array_merge($populatedData, $patternData);
        
        // Rule-based auto-population
        $ruleData = $this->ruleEngine->autoPopulateFields($template, $context);
        $populatedData = array_merge($populatedData, $ruleData);
        
        // AI-enhanced auto-population (if available)
        if ($this->aiService && $this->aiService->isAvailable()) {
            try {
                $aiData = $this->aiService->autoPopulateFields($template, $context);
                $populatedData = array_merge($populatedData, $aiData);
            } catch (\Exception $e) {
                Log::warning('AI auto-population failed', ['error' => $e->getMessage()]);
            }
        }
        
        return $populatedData;
    }

    /**
     * Check compliance against methodology
     */
    public function checkCompliance(array $formData, string $methodology): array
    {
        $checker = $this->getComplianceChecker($methodology);
        return $checker->checkCompliance($formData);
    }

    /**
     * Get next recommended action for workflow
     */
    public function getNextWorkflowAction(FormInstance $instance): array
    {
        $recommendations = [];
        
        // Rule-based workflow recommendations
        $ruleRecommendations = $this->ruleEngine->getWorkflowRecommendations($instance);
        $recommendations = array_merge($recommendations, $ruleRecommendations);
        
        // AI-enhanced workflow recommendations (if available)
        if ($this->aiService && $this->aiService->isAvailable()) {
            try {
                $aiRecommendations = $this->aiService->getWorkflowRecommendations($instance);
                $recommendations = array_merge($recommendations, $aiRecommendations);
            } catch (\Exception $e) {
                Log::warning('AI workflow recommendations failed', ['error' => $e->getMessage()]);
            }
        }
        
        return [
            'recommendations' => $recommendations,
            'next_step' => $this->determineNextStep($instance),
            'required_actions' => $this->getRequiredActions($instance)
        ];
    }

    private function getComplianceChecker(string $methodology): ComplianceCheckerInterface
    {
        return match($methodology) {
            'usaid' => new USAIDComplianceChecker(),
            'world_bank' => new WorldBankComplianceChecker(),
            'eu' => new EUComplianceChecker(),
            default => new DefaultComplianceChecker()
        };
    }

    private function determineNextStep(FormInstance $instance): ?string
    {
        $template = $instance->template;
        $workflowConfig = $template->workflow_configuration;
        
        if (!$workflowConfig) {
            return null;
        }
        
        return $this->ruleEngine->determineNextStep($workflowConfig, $instance->form_data);
    }

    private function getRequiredActions(FormInstance $instance): array
    {
        $actions = [];
        $validation = $this->validateFormData($instance->form_data, $instance->template);
        
        if (!$validation['valid']) {
            $actions[] = [
                'type' => 'validation',
                'description' => 'Fix validation errors before proceeding',
                'priority' => 'high',
                'errors' => $validation['errors']
            ];
        }
        
        if ($instance->completion_percentage < 100) {
            $actions[] = [
                'type' => 'completion',
                'description' => 'Complete all required fields',
                'priority' => 'medium',
                'completion' => $instance->completion_percentage
            ];
        }
        
        return $actions;
    }
}


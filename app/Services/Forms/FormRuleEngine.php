<?php 
namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Support\Facades\Log;

class FormRuleEngine
{
    /**
     * Generate rule-based suggestions
     */
    public function generateSuggestions(string $fieldId, array $context): array
    {
        $suggestions = [];
        
        // Rule-based logic for specific fields
        switch ($fieldId) {
            case 'project_code':
                $suggestions = $this->generateProjectCodes($context);
                break;
                
            case 'end_date':
                if (isset($context['start_date']) && isset($context['duration'])) {
                    $suggestions = $this->calculateEndDates($context['start_date'], $context['duration']);
                }
                break;
                
            case 'team_size':
                if (isset($context['budget'])) {
                    $suggestions = $this->suggestTeamSize($context['budget']);
                }
                break;
                
            case 'risk_level':
                $suggestions = $this->assessRiskLevel($context);
                break;
        }
        
        return $suggestions;
    }

    /**
     * Calculate field values based on formulas
     */
    public function calculateField(string $formula, array $formData): mixed
    {
        // Simple formula parser for common calculations
        // Supports basic arithmetic, date calculations, and conditional logic
        
        try {
            // Replace field references with actual values
            $processedFormula = $this->replaceFieldReferences($formula, $formData);
            
            // Handle different formula types
            if (str_starts_with($formula, 'IF(')) {
                return $this->evaluateConditional($processedFormula, $formData);
            } elseif (str_contains($formula, 'DATE_ADD')) {
                return $this->evaluateDateCalculation($processedFormula, $formData);
            } else {
                return $this->evaluateArithmetic($processedFormula);
            }
        } catch (\Exception $e) {
            Log::warning('Formula calculation failed', [
                'formula' => $formula,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Auto-populate fields based on rules
     */
    public function autoPopulateFields(FormTemplate $template, array $context): array
    {
        $populatedData = [];
        
        // Apply auto-population rules
        foreach ($template->getAllFields() as $fieldId => $field) {
            $autoPopulateRule = $field['auto_populate'] ?? null;
            
            if ($autoPopulateRule) {
                $value = $this->applyAutoPopulateRule($autoPopulateRule, $context);
                if ($value !== null) {
                    $populatedData[$fieldId] = $value;
                }
            }
        }
        
        return $populatedData;
    }

    /**
     * Determine next workflow step
     */
    public function determineNextStep(array $workflowConfig, array $formData): string
    {
        $steps = $workflowConfig['steps'] ?? [];
        $conditions = $workflowConfig['conditions'] ?? [];
        
        foreach ($conditions as $condition) {
            if ($this->evaluateCondition($condition['condition'], $formData)) {
                return $condition['next_step'];
            }
        }
        
        // Default to next step in sequence
        return $workflowConfig['default_next_step'] ?? 'review';
    }

    /**
     * Get workflow recommendations
     */
    public function getWorkflowRecommendations(FormInstance $instance): array
    {
        $recommendations = [];
        $formData = $instance->form_data;
        $template = $instance->template;
        
        // Check for missing required fields
        $missingFields = $this->getMissingRequiredFields($template, $formData);
        if (!empty($missingFields)) {
            $recommendations[] = [
                'type' => 'completion',
                'priority' => 'high',
                'message' => 'Complete required fields: ' . implode(', ', $missingFields)
            ];
        }
        
        // Check business rules
        $businessRuleViolations = $this->checkBusinessRules($template, $formData);
        foreach ($businessRuleViolations as $violation) {
            $recommendations[] = [
                'type' => 'business_rule',
                'priority' => 'medium',
                'message' => $violation
            ];
        }
        
        // Check compliance requirements
        $complianceIssues = $this->checkComplianceRequirements($template, $formData);
        foreach ($complianceIssues as $issue) {
            $recommendations[] = [
                'type' => 'compliance',
                'priority' => 'high',
                'message' => $issue
            ];
        }
        
        return $recommendations;
    }

    private function generateProjectCodes(array $context): array
    {
        $prefix = strtoupper(substr($context['category'] ?? 'PROJ', 0, 3));
        $year = date('Y');
        $codes = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $codes[] = $prefix . '-' . $year . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
        }
        
        return $codes;
    }

    private function calculateEndDates(string $startDate, string $duration): array
    {
        $start = new \DateTime($startDate);
        $suggestions = [];
        
        // Parse duration (e.g., "12 months", "18 months")
        if (preg_match('/(\d+)\s*(month|year)s?/i', $duration, $matches)) {
            $amount = (int) $matches[1];
            $unit = strtolower($matches[2]);
            
            $interval = $unit === 'year' ? "P{$amount}Y" : "P{$amount}M";
            $endDate = clone $start;
            $endDate->add(new \DateInterval($interval));
            
            $suggestions[] = $endDate->format('Y-m-d');
        }
        
        return $suggestions;
    }

    private function suggestTeamSize(float $budget): array
    {
        // Rule-based team size suggestions based on budget
        if ($budget < 50000) {
            return [2, 3, 4];
        } elseif ($budget < 200000) {
            return [4, 5, 6, 7];
        } elseif ($budget < 500000) {
            return [6, 8, 10, 12];
        } else {
            return [10, 15, 20, 25];
        }
    }

    private function assessRiskLevel(array $context): array
    {
        $riskFactors = 0;
        
        // Budget risk
        $budget = $context['budget'] ?? 0;
        if ($budget > 1000000) $riskFactors += 2;
        elseif ($budget > 500000) $riskFactors += 1;
        
        // Duration risk
        $duration = $context['duration'] ?? '';
        if (str_contains($duration, 'year') && (int) $duration >= 3) $riskFactors += 2;
        elseif (str_contains($duration, 'month') && (int) $duration >= 18) $riskFactors += 1;
        
        // Location risk (if international)
        $location = $context['location'] ?? '';
        if (str_contains(strtolower($location), 'international')) $riskFactors += 1;
        
        if ($riskFactors >= 4) return ['High'];
        elseif ($riskFactors >= 2) return ['Medium'];
        else return ['Low'];
    }

    private function replaceFieldReferences(string $formula, array $formData): string
    {
        // Replace field references like {field_name} with actual values
        return preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($formData) {
            $fieldName = $matches[1];
            return $formData[$fieldName] ?? 0;
        }, $formula);
    }

    private function evaluateConditional(string $formula, array $formData): mixed
    {
        // Simple IF condition evaluation
        // Example: IF(budget > 100000, 'high', 'normal')
        
        if (preg_match('/IF\(([^,]+),\s*([^,]+),\s*([^)]+)\)/', $formula, $matches)) {
            $condition = trim($matches[1]);
            $trueValue = trim($matches[2], '"\'');
            $falseValue = trim($matches[3], '"\'');
            
            // Evaluate condition (simplified)
            $conditionResult = $this->evaluateSimpleCondition($condition, $formData);
            
            return $conditionResult ? $trueValue : $falseValue;
        }
        
        return null;
    }

    private function evaluateDateCalculation(string $formula, array $formData): ?string
    {
        // Example: DATE_ADD(start_date, INTERVAL 12 MONTH)
        if (preg_match('/DATE_ADD\(([^,]+),\s*INTERVAL\s+(\d+)\s+(\w+)\)/', $formula, $matches)) {
            $dateField = trim($matches[1]);
            $amount = (int) $matches[2];
            $unit = strtoupper($matches[3]);
            
            $date = new \DateTime($formData[$dateField] ?? 'now');
            
            switch ($unit) {
                case 'DAY':
                case 'DAYS':
                    $date->add(new \DateInterval("P{$amount}D"));
                    break;
                case 'MONTH':
                case 'MONTHS':
                    $date->add(new \DateInterval("P{$amount}M"));
                    break;
                case 'YEAR':
                case 'YEARS':
                    $date->add(new \DateInterval("P{$amount}Y"));
                    break;
            }
            
            return $date->format('Y-m-d');
        }
        
        return null;
    }

    private function evaluateArithmetic(string $formula): mixed
    {
        // Simple arithmetic evaluation (be very careful with eval!)
        // Only allow basic math operations
        $sanitizedFormula = preg_replace('/[^0-9+\-*\/\.\(\)\s]/', '', $formula);
        
        if ($sanitizedFormula === $formula) {
            try {
                return eval("return $sanitizedFormula;");
            } catch (\ParseError $e) {
                return null;
            }
        }
        
        return null;
    }

    private function applyAutoPopulateRule(array $rule, array $context): mixed
    {
        $ruleType = $rule['type'] ?? '';
        
        switch ($ruleType) {
            case 'current_date':
                return date('Y-m-d');
                
            case 'current_user':
                return auth()->id();
                
            case 'lookup':
                return $this->performLookup($rule['lookup'], $context);
                
            case 'calculation':
                return $this->calculateField($rule['formula'], $context);
                
            default:
                return $rule['default_value'] ?? null;
        }
    }

    private function performLookup(array $lookup, array $context): mixed
    {
        // Simplified lookup implementation
        $table = $lookup['table'] ?? '';
        $field = $lookup['field'] ?? '';
        $where = $lookup['where'] ?? [];
        
        // This would typically use the query builder
        // For now, return a placeholder
        return null;
    }

    private function evaluateCondition(string $condition, array $formData): bool
    {
        // Simple condition evaluation
        // Replace field references
        $processedCondition = $this->replaceFieldReferences($condition, $formData);
        
        // Evaluate simple comparisons
        return $this->evaluateSimpleCondition($processedCondition, $formData);
    }

    private function evaluateSimpleCondition(string $condition, array $formData): bool
    {
        // Very basic condition evaluation
        // In production, use a proper expression evaluator
        
        if (preg_match('/(\w+)\s*(>|<|>=|<=|==|!=)\s*(.+)/', $condition, $matches)) {
            $field = trim($matches[1]);
            $operator = trim($matches[2]);
            $value = trim($matches[3], '"\'');
            
            $fieldValue = $formData[$field] ?? null;
            
            switch ($operator) {
                case '>':
                    return $fieldValue > $value;
                case '<':
                    return $fieldValue < $value;
                case '>=':
                    return $fieldValue >= $value;
                case '<=':
                    return $fieldValue <= $value;
                case '==':
                    return $fieldValue == $value;
                case '!=':
                    return $fieldValue != $value;
            }
        }
        
        return false;
    }

    private function getMissingRequiredFields(FormTemplate $template, array $formData): array
    {
        $missing = [];
        
        foreach ($template->getAllFields() as $fieldId => $field) {
            if (($field['required'] ?? false) && empty($formData[$fieldId])) {
                $missing[] = $field['label'] ?? $fieldId;
            }
        }
        
        return $missing;
    }

    private function checkBusinessRules(FormTemplate $template, array $formData): array
    {
        $violations = [];
        $rules = $template->validation_rules ?? [];
        
        foreach ($rules as $rule) {
            if (($rule['rule_type'] ?? '') === 'business_logic') {
                $condition = $rule['conditions'] ?? '';
                if (!$this->evaluateCondition($condition, $formData)) {
                    $violations[] = $rule['error_message'] ?? 'Business rule violation';
                }
            }
        }
        
        return $violations;
    }

    private function checkComplianceRequirements(FormTemplate $template, array $formData): array
    {
        $issues = [];
        $methodology = $template->methodology_type;
        
        // Basic compliance checks based on methodology
        switch ($methodology) {
            case 'usaid':
                if (empty($formData['environmental_screening'])) {
                    $issues[] = 'Environmental screening is required for USAID projects';
                }
                if (empty($formData['gender_integration'])) {
                    $issues[] = 'Gender integration analysis is required';
                }
                break;
                
            case 'world_bank':
                if (empty($formData['safeguards_screening'])) {
                    $issues[] = 'Safeguards screening is required for World Bank projects';
                }
                break;
                
            case 'eu':
                if (empty($formData['logical_framework'])) {
                    $issues[] = 'Logical framework is required for EU projects';
                }
                break;
        }
        
        return $issues;
    }
}
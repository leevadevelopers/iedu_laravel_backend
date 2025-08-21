<? 
// File: app/Services/Forms/Compliance/USAIDComplianceChecker.php
namespace App\Services\Forms\Compliance;

class USAIDComplianceChecker implements ComplianceCheckerInterface
{
    private const REQUIRED_FIELDS = [
        'environmental_screening',
        'gender_integration',
        'marking_branding_plan'
    ];

    private const CONDITIONAL_REQUIREMENTS = [
        'budget_threshold_requirements' => [
            'condition' => 'budget > 100000',
            'required_fields' => ['detailed_budget_breakdown', 'cost_share_documentation']
        ],
        'construction_requirements' => [
            'condition' => 'category = construction',
            'required_fields' => ['environmental_assessment', 'land_rights_documentation']
        ],
        'capacity_building_requirements' => [
            'condition' => 'category = capacity_building',
            'required_fields' => ['training_plan', 'sustainability_plan']
        ]
    ];

    public function validateCompliance(array $formData): array
    {
        $errors = [];
        $warnings = [];
        $score = 100.0;

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($formData[$field])) {
                $errors[] = $this->getFieldErrorMessage($field);
                $score -= 20;
            }
        }

        // Check conditional requirements
        foreach (self::CONDITIONAL_REQUIREMENTS as $requirementName => $requirement) {
            if ($this->evaluateCondition($requirement['condition'], $formData)) {
                foreach ($requirement['required_fields'] as $field) {
                    if (empty($formData[$field])) {
                        $errors[] = $this->getConditionalErrorMessage($field, $requirementName);
                        $score -= 15;
                    }
                }
            }
        }

        // Check USAID-specific business rules
        $businessRuleViolations = $this->checkUSAIDBusinessRules($formData);
        foreach ($businessRuleViolations as $violation) {
            if ($violation['severity'] === 'error') {
                $errors[] = $violation['message'];
                $score -= $violation['penalty'];
            } else {
                $warnings[] = $violation['message'];
                $score -= $violation['penalty'] / 2;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'score' => max(0, $score)
        ];
    }

    public function getRequiredFields(): array
    {
        return self::REQUIRED_FIELDS;
    }

    public function getComplianceScore(array $formData): float
    {
        $result = $this->validateCompliance($formData);
        return $result['score'];
    }

    private function evaluateCondition(string $condition, array $formData): bool
    {
        // Simple condition evaluation for USAID rules
        if (preg_match('/(\w+)\s*(>|<|=)\s*(.+)/', $condition, $matches)) {
            $field = $matches[1];
            $operator = $matches[2];
            $value = $matches[3];
            
            $fieldValue = $formData[$field] ?? null;
            
            switch ($operator) {
                case '>':
                    return (float) $fieldValue > (float) $value;
                case '<':
                    return (float) $fieldValue < (float) $value;
                case '=':
                    return $fieldValue == $value;
            }
        }
        
        return false;
    }

    private function checkUSAIDBusinessRules(array $formData): array
    {
        $violations = [];

        // Gender integration percentage check
        $genderPercentage = (float) ($formData['female_participation_percentage'] ?? 0);
        if ($genderPercentage < 30) {
            $violations[] = [
                'message' => 'USAID requires minimum 30% female participation',
                'severity' => 'warning',
                'penalty' => 10
            ];
        }

        // Environmental screening category check
        $envCategory = $formData['environmental_category'] ?? '';
        $budget = (float) ($formData['budget'] ?? 0);
        
        if ($budget > 500000 && $envCategory === 'categorical_exclusion') {
            $violations[] = [
                'message' => 'High-budget projects cannot use categorical exclusion',
                'severity' => 'error',
                'penalty' => 25
            ];
        }

        // Marking and branding compliance
        $hasMarkingPlan = !empty($formData['marking_branding_plan']);
        $hasUSAIDLogo = !empty($formData['usaid_logo_usage']);
        
        if ($hasMarkingPlan && !$hasUSAIDLogo) {
            $violations[] = [
                'message' => 'USAID logo usage must be specified in marking plan',
                'severity' => 'error',
                'penalty' => 15
            ];
        }

        // Duration limits
        $duration = $this->parseDuration($formData['duration'] ?? '');
        if ($duration > 60) { // 5 years in months
            $violations[] = [
                'message' => 'USAID projects typically should not exceed 5 years',
                'severity' => 'warning',
                'penalty' => 5
            ];
        }

        return $violations;
    }

    private function parseDuration(string $duration): int
    {
        if (preg_match('/(\d+)\s*(month|year)s?/i', $duration, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);
            return $unit === 'year' ? $value * 12 : $value;
        }
        return 0;
    }

    private function getFieldErrorMessage(string $field): string
    {
        $messages = [
            'environmental_screening' => 'Environmental screening is mandatory for all USAID projects',
            'gender_integration' => 'Gender integration analysis is required per USAID policy',
            'marking_branding_plan' => 'USAID marking and branding plan is required'
        ];

        return $messages[$field] ?? "Required field '{$field}' is missing";
    }

    private function getConditionalErrorMessage(string $field, string $requirement): string
    {
        $messages = [
            'detailed_budget_breakdown' => 'Detailed budget breakdown required for projects over $100,000',
            'cost_share_documentation' => 'Cost share documentation required for high-value projects',
            'environmental_assessment' => 'Environmental assessment required for construction projects',
            'land_rights_documentation' => 'Land rights documentation required for construction projects',
            'training_plan' => 'Training plan required for capacity building projects',
            'sustainability_plan' => 'Sustainability plan required for capacity building projects'
        ];

        return $messages[$field] ?? "Conditional field '{$field}' is required";
    }
}

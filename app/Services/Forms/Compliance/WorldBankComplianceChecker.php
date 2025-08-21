<? 
// File: app/Services/Forms/Compliance/WorldBankComplianceChecker.php
namespace App\Services\Forms\Compliance;

class WorldBankComplianceChecker implements ComplianceCheckerInterface
{
    private const REQUIRED_FIELDS = [
        'project_development_objective',
        'results_framework',
        'safeguards_screening'
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
                $score -= 25;
            }
        }

        // Check safeguards policies
        $safeguardsViolations = $this->checkSafeguardsPolicies($formData);
        foreach ($safeguardsViolations as $violation) {
            $errors[] = $violation['message'];
            $score -= $violation['penalty'];
        }

        // Check results framework
        $resultsFrameworkIssues = $this->validateResultsFramework($formData);
        foreach ($resultsFrameworkIssues as $issue) {
            if ($issue['severity'] === 'error') {
                $errors[] = $issue['message'];
                $score -= $issue['penalty'];
            } else {
                $warnings[] = $issue['message'];
                $score -= $issue['penalty'] / 2;
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

    private function checkSafeguardsPolicies(array $formData): array
    {
        $violations = [];
        $safeguardsCategory = $formData['safeguards_category'] ?? '';

        // OP 4.01 Environmental Assessment
        if (in_array($safeguardsCategory, ['A', 'B']) && empty($formData['environmental_assessment'])) {
            $violations[] = [
                'message' => 'Environmental assessment required for Category A or B projects (OP 4.01)',
                'penalty' => 30
            ];
        }

        // OP 4.12 Involuntary Resettlement
        if (!empty($formData['involves_resettlement']) && empty($formData['resettlement_plan'])) {
            $violations[] = [
                'message' => 'Resettlement plan required when project involves involuntary resettlement (OP 4.12)',
                'penalty' => 25
            ];
        }

        // OP 4.10 Indigenous Peoples
        if (!empty($formData['affects_indigenous_peoples']) && empty($formData['indigenous_peoples_plan'])) {
            $violations[] = [
                'message' => 'Indigenous Peoples Plan required when project affects indigenous peoples (OP 4.10)',
                'penalty' => 25
            ];
        }

        return $violations;
    }

    private function validateResultsFramework(array $formData): array
    {
        $issues = [];
        $resultsFramework = $formData['results_framework'] ?? [];

        if (empty($resultsFramework)) {
            return $issues;
        }

        // Check PDO indicators
        $pdoIndicators = $resultsFramework['pdo_indicators'] ?? [];
        if (empty($pdoIndicators)) {
            $issues[] = [
                'message' => 'Project Development Objective indicators are required',
                'severity' => 'error',
                'penalty' => 20
            ];
        } else {
            foreach ($pdoIndicators as $indicator) {
                if (empty($indicator['baseline']) || empty($indicator['target'])) {
                    $issues[] = [
                        'message' => 'All PDO indicators must have baseline and target values',
                        'severity' => 'error',
                        'penalty' => 15
                    ];
                }
            }
        }

        // Check intermediate results indicators
        $intermediateIndicators = $resultsFramework['intermediate_indicators'] ?? [];
        if (empty($intermediateIndicators)) {
            $issues[] = [
                'message' => 'Intermediate results indicators should be defined',
                'severity' => 'warning',
                'penalty' => 10
            ];
        }

        // Validate results chain logic
        if (!$this->validateResultsChainLogic($resultsFramework)) {
            $issues[] = [
                'message' => 'Results chain logic needs improvement',
                'severity' => 'warning',
                'penalty' => 10
            ];
        }

        return $issues;
    }

    private function validateResultsChainLogic(array $resultsFramework): bool
    {
        // Simplified validation of results chain logic
        $activities = $resultsFramework['activities'] ?? [];
        $outputs = $resultsFramework['outputs'] ?? [];
        $outcomes = $resultsFramework['outcomes'] ?? [];

        return !empty($activities) && !empty($outputs) && !empty($outcomes);
    }

    private function getFieldErrorMessage(string $field): string
    {
        $messages = [
            'project_development_objective' => 'Project Development Objective (PDO) statement is required',
            'results_framework' => 'Results framework with indicators is mandatory',
            'safeguards_screening' => 'Environmental and social safeguards screening is required'
        ];

        return $messages[$field] ?? "Required field '{$field}' is missing";
    }
}
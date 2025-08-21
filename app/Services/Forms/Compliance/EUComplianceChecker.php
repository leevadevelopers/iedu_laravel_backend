<? 
// File: app/Services/Forms/Compliance/EUComplianceChecker.php
namespace App\Services\Forms\Compliance;

class EUComplianceChecker implements ComplianceCheckerInterface
{
    private const REQUIRED_FIELDS = [
        'logical_framework',
        'sustainability_plan',
        'visibility_plan'
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

        // Check logical framework
        $logFrameIssues = $this->validateLogicalFramework($formData);
        foreach ($logFrameIssues as $issue) {
            if ($issue['severity'] === 'error') {
                $errors[] = $issue['message'];
                $score -= $issue['penalty'];
            } else {
                $warnings[] = $issue['message'];
                $score -= $issue['penalty'] / 2;
            }
        }

        // Check cross-cutting themes
        $crossCuttingIssues = $this->validateCrossCuttingThemes($formData);
        foreach ($crossCuttingIssues as $issue) {
            $warnings[] = $issue['message'];
            $score -= $issue['penalty'];
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

    private function validateLogicalFramework(array $formData): array
    {
        $issues = [];
        $logFrame = $formData['logical_framework'] ?? [];

        if (empty($logFrame)) {
            return $issues;
        }

        // Check hierarchy completeness
        $requiredLevels = ['overall_objective', 'specific_objectives', 'expected_results', 'activities'];
        foreach ($requiredLevels as $level) {
            if (empty($logFrame[$level])) {
                $issues[] = [
                    'message' => "Logical framework must include {$level}",
                    'severity' => 'error',
                    'penalty' => 20
                ];
            }
        }

        // Check indicators
        if (!empty($logFrame['specific_objectives'])) {
            foreach ($logFrame['specific_objectives'] as $objective) {
                if (empty($objective['indicators'])) {
                    $issues[] = [
                        'message' => 'All specific objectives must have measurable indicators',
                        'severity' => 'error',
                        'penalty' => 15
                    ];
                }
            }
        }

        // Check assumptions
        if (empty($logFrame['assumptions'])) {
            $issues[] = [
                'message' => 'Logical framework should include key assumptions',
                'severity' => 'warning',
                'penalty' => 10
            ];
        }

        return $issues;
    }

    private function validateCrossCuttingThemes(array $formData): array
    {
        $issues = [];

        // Gender equality
        $genderIntegration = $formData['gender_integration_score'] ?? 0;
        if ($genderIntegration < 2) {
            $issues[] = [
                'message' => 'EU requires meaningful gender integration (minimum score 2)',
                'penalty' => 10
            ];
        }

        // Environmental sustainability
        $envSustainability = $formData['environmental_sustainability_score'] ?? 0;
        if ($envSustainability < 2) {
            $issues[] = [
                'message' => 'Environmental sustainability should be addressed (minimum score 2)',
                'penalty' => 10
            ];
        }

        // Human rights
        if (empty($formData['human_rights_analysis'])) {
            $issues[] = [
                'message' => 'Human rights analysis should be included for EU projects',
                'penalty' => 5
            ];
        }

        return $issues;
    }

    private function getFieldErrorMessage(string $field): string
    {
        $messages = [
            'logical_framework' => 'Logical framework (LogFrame) is mandatory for EU projects',
            'sustainability_plan' => 'Sustainability plan is required to ensure project continuity',
            'visibility_plan' => 'EU visibility and communication plan is mandatory'
        ];

        return $messages[$field] ?? "Required field '{$field}' is missing";
    }
}
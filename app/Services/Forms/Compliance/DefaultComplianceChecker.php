<? 
// File: app/Services/Forms/Compliance/DefaultComplianceChecker.php
namespace App\Services\Forms\Compliance;

class DefaultComplianceChecker implements ComplianceCheckerInterface
{
    public function validateCompliance(array $formData): array
    {
        return [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'score' => 100.0
        ];
    }

    public function getRequiredFields(): array
    {
        return [];
    }

    public function getComplianceScore(array $formData): float
    {
        return 100.0;
    }
}
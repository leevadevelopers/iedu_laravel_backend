<?php 
// File: app/Services/Forms/Compliance/ComplianceCheckerInterface.php
namespace App\Services\Forms\Compliance;

interface ComplianceCheckerInterface
{
    public function validateCompliance(array $formData): array;
    public function getRequiredFields(): array;
    public function getComplianceScore(array $formData): float;
}


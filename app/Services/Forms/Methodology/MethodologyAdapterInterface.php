<?php 
// File: app/Services/Forms/Methodology/MethodologyAdapterInterface.php
namespace App\Services\Forms\Methodology;

interface MethodologyAdapterInterface
{
    public function adaptTemplate(array $templateData): array;
    public function getRequirements(): array;
    public function getComplianceConfiguration(): array;
}

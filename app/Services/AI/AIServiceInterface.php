<?php 
namespace App\Services\AI;

interface AIServiceInterface
{
    public function isAvailable(): bool;
    public function generateFieldSuggestions(string $fieldId, array $context): array;
    public function validateFormData(array $formData, $template): array;
    public function autoPopulateFields($template, array $context): array;
    public function getWorkflowRecommendations($instance): array;
}
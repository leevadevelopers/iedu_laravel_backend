<?php

namespace App\Services\AI;

class NullAIService implements AIServiceInterface
{
    public function isAvailable(): bool { return false; }
    public function generateFieldSuggestions(string $fieldId, array $context): array { return []; }
    public function validateFormData(array $formData, $template): array { 
        return ['valid' => true, 'errors' => [], 'warnings' => []]; 
    }
    public function autoPopulateFields($template, array $context): array { return []; }
    public function getWorkflowRecommendations($instance): array { return []; }
}
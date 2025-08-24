<?php

namespace App\Services;

use App\Models\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FormEngineService
{
    /**
     * Process form data through the form engine.
     */
    public function processFormData(string $formType, array $data): array
    {
        // Get the form template
        $template = $this->getFormTemplate($formType);

        if (!$template) {
            throw new \Exception("Form template not found for type: {$formType}");
        }

        // Validate the data against the template
        $this->validateFormData($template, $data);

        // Apply AI enhancements if available
        $processedData = $this->applyAiEnhancements($template, $data);

        return $processedData;
    }

    /**
     * Create a form instance from processed data.
     */
    public function createFormInstance(string $formType, array $data, ?string $relatedEntityType = null, ?int $relatedEntityId = null): FormInstance
    {
        $template = $this->getFormTemplate($formType);

        $instance = \App\Models\Forms\FormInstance::create([
            'form_template_id' => $template->id,
            'organization_id' => $data['organization_id'],
            'instance_name' => $data['name'] ?? "Form Instance - {$formType}",
            'reference_number' => $this->generateReferenceNumber($formType),
            'data_json' => $data,
            'submitted_by' => Auth::id(),
            'submission_date' => now(),
            'status' => 'submitted',
            'validation_status' => 'valid',
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId
        ]);

        return $instance;
    }

    /**
     * Get form template by type.
     */
    private function getFormTemplate(string $formType): ?FormTemplate
    {
        return FormTemplate::where('category', $formType)
            ->where('is_active', true)
            // ->where('organization_id', $data['organization_id'])
            // ->orWhere('is_system_template', true)
            ->first();
    }

    /**
     * Validate form data against template rules.
     */
    private function validateFormData(FormTemplate $template, array $data): void
    {
        $rules = $template->validation_rules_json ?? [];

        if (!empty($rules)) {
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }
    }

    /**
     * Apply AI enhancements if available.
     */
    private function applyAiEnhancements(FormTemplate $template, array $data): array
    {
        if (!$this->isAiEnabled()) {
            return $data;
        }

        try {
            // Apply AI-based field completion
            $data = $this->applyAiFieldCompletion($template, $data);

            // Apply AI validation scoring
            $data['ai_validation_score'] = $this->calculateAiValidationScore($data);

        } catch (\Exception $e) {
            // Graceful degradation - log error but continue
            Log::warning('AI enhancement failed', ['error' => $e->getMessage()]);
        }

        return $data;
    }

    /**
     * Check if AI is enabled.
     */
    private function isAiEnabled(): bool
    {
        // Temporarily disable AI to avoid config access during update operations
        // This prevents circular dependency with the config service
        return false;

        // Original implementation (commented out to prevent circular dependency):
        // return config('app.ai_enabled', false) && Auth::user()->organization->hasAiFeatures();
    }

    /**
     * Apply AI-based field completion.
     */
    private function applyAiFieldCompletion(FormTemplate $template, array $data): array
    {
        // Placeholder for AI field completion logic
        return $data;
    }

    /**
     * Calculate AI validation score.
     */
    private function calculateAiValidationScore(array $data): float
    {
        // Placeholder for AI validation scoring
        return 0.85; // Return a score between 0 and 1
    }

    /**
     * Generate reference number for form instance.
     */
    private function generateReferenceNumber(string $formType): string
    {
        $prefix = match($formType) {
            'project_creation' => 'PRJ',
            'budget_planning' => 'BUD',
            'procurement_request' => 'PRO',
            'contract_management' => 'CON',
            'risk_assessment' => 'RSK',
            'me_data_collection' => 'MEV',
            'compliance_check' => 'CMP',
            default => 'FRM'
        };

        $year = now()->year;
                    $sequence = \App\Models\Forms\FormInstance::where('reference_number', 'like', "{$prefix}-{$year}-%")
            ->count() + 1;

        return $prefix . '-' . $year . '-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}

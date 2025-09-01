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
            // School Management Categories
            'school_registration' => 'SCH',
            'school_enrollment' => 'SEN',
            'school_setup' => 'SSU',

            // Student Management Categories
            'student_enrollment' => 'ENR',
            'student_registration' => 'REG',
            'student_transfer' => 'TRF',
            'attendance' => 'ATT',
            'grades' => 'GRD',
            'academic_records' => 'ACR',
            'behavior_incident' => 'BHV',
            'parent_communication' => 'PCM',
            'teacher_evaluation' => 'TEV',
            'curriculum_planning' => 'CRP',
            'extracurricular' => 'EXT',
            'field_trip' => 'FDT',
            'parent_meeting' => 'PMT',
            'student_health' => 'HLT',
            'special_education' => 'SPE',
            'discipline' => 'DSC',
            'graduation' => 'GRD',
            'scholarship' => 'SLR',

            // Staff Management Categories
            'staff_management' => 'STM',
            'faculty_recruitment' => 'FCR',
            'professional_development' => 'PFD',

            // Administrative Categories
            'school_calendar' => 'SCL',
            'events_management' => 'EVM',
            'facilities_management' => 'FCM',
            'transportation' => 'TRN',
            'cafeteria_management' => 'CFM',
            'library_management' => 'LBM',
            'technology_management' => 'TCM',
            'security_management' => 'SCM',
            'maintenance_requests' => 'MNR',

            // Financial Categories
            'financial_aid' => 'FNA',
            'tuition_management' => 'TUM',
            'donation_management' => 'DNM',

            // Community Categories
            'alumni_relations' => 'ALR',
            'community_outreach' => 'CMO',
            'partnership_management' => 'PTM',

            default => 'FRM'
        };

        $year = now()->year;
        $timestamp = now()->timestamp;
        $random = mt_rand(1000, 9999);

        // Use timestamp and random number instead of counting database records
        return $prefix . '-' . $year . '-' . $timestamp . '-' . $random;
    }
}

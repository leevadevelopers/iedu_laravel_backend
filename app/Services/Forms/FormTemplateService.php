<?php

// =====================================================
// FORM TEMPLATE SERVICES
// =====================================================

// File: app/Services/Forms/FormTemplateService.php
namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormTemplateService
{
    protected $methodologyAdapter;

    public function __construct(MethodologyAdapterService $methodologyAdapter)
    {
        $this->methodologyAdapter = $methodologyAdapter;
    }

    /**
     * Get templates for organization with caching
     */
    public function getOrgTemplates(int $tenantId, ?string $category = null, ?string $methodology = null): Collection
    {
        $cacheKey = "org_templates_{$tenantId}_{$category}_{$methodology}";
        
        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $category, $methodology) {
            $query = FormTemplate::where('tenant_id', $tenantId)
                ->active()
                ->orderBy('is_default', 'desc')
                ->orderBy('name');

            if ($category) {
                $query->where('category', $category);
            }

            if ($methodology) {
                $query->where(function ($q) use ($methodology) {
                    $q->where('methodology_type', $methodology)
                      ->orWhere('methodology_type', 'universal');
                });
            }

            return $query->with('creator')->get();
        });
    }

    /**
     * Load template with full configuration
     */
    public function loadTemplate(int $templateId): FormTemplate
    {
        $cacheKey = "form_template_{$templateId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($templateId) {
            return FormTemplate::with(['creator', 'versions'])
                ->findOrFail($templateId);
        });
    }

    /**
     * Create new form template
     */
    public function createTemplate(int $tenantId, array $templateData): FormTemplate
    {
        return DB::transaction(function () use ($tenantId, $templateData) {
            // Validate template structure
            $this->validateTemplateStructure($templateData);
            
            // Apply methodology adaptations if specified
            if (!empty($templateData['methodology_type']) && $templateData['methodology_type'] !== 'universal') {
                $templateData = $this->methodologyAdapter->adaptTemplate($templateData, $templateData['methodology_type']);
            }
            
            // Ensure default values
            $templateData = array_merge([
                'tenant_id' => $tenantId,
                'version' => '1.0',
                'is_active' => true,
                'is_default' => false,
                'auto_save' => true,
                'compliance_level' => 'standard',
                'created_by' => auth()->id(),
            ], $templateData);
            
            $template = FormTemplate::create($templateData);
            
            // Create initial version
            $template->createVersion('Initial template creation', auth()->id());
            
            // Clear template cache
            $this->clearTemplateCache($tenantId);
            
            return $template;
        });
    }

    /**
     * Customize existing template for organization
     */
    public function customizeTemplate(FormTemplate $baseTemplate, array $customizations, int $tenantId): FormTemplate
    {
        return DB::transaction(function () use ($baseTemplate, $customizations, $tenantId) {
            // Create customized template data
            $templateData = $baseTemplate->toArray();
            unset($templateData['id'], $templateData['created_at'], $templateData['updated_at']);
            
            // Apply customizations
            $templateData = array_merge($templateData, $customizations);
            $templateData['tenant_id'] = $tenantId;
            $templateData['name'] = $customizations['name'] ?? $baseTemplate->name . ' (Customized)';
            $templateData['is_default'] = false;
            $templateData['version'] = '1.0';
            $templateData['created_by'] = auth()->id();
            
            // Apply methodology adaptations if changed
            if (isset($customizations['methodology_type']) && 
                $customizations['methodology_type'] !== $baseTemplate->methodology_type) {
                $templateData = $this->methodologyAdapter->adaptTemplate($templateData, $customizations['methodology_type']);
            }
            
            $customizedTemplate = FormTemplate::create($templateData);
            
            // Create version tracking
            $customizedTemplate->createVersion('Customized from template: ' . $baseTemplate->name, auth()->id());
            
            $this->clearTemplateCache($tenantId);
            
            return $customizedTemplate;
        });
    }

    /**
     * Update existing template
     */
    public function updateTemplate(FormTemplate $template, array $updates): FormTemplate
    {
        return DB::transaction(function () use ($template, $updates) {
            $originalData = $template->toArray();
            
            // Apply methodology adaptations if methodology changed
            if (isset($updates['methodology_type']) && 
                $updates['methodology_type'] !== $template->methodology_type) {
                $updates = $this->methodologyAdapter->adaptTemplate($updates, $updates['methodology_type']);
            }
            
            $template->update($updates);
            
            // Create version if significant changes
            if ($this->hasSignificantChanges($originalData, $template->toArray())) {
                $changesSummary = $this->generateChangesSummary($originalData, $template->toArray());
                $template->createVersion($changesSummary, auth()->id());
            }
            
            $this->clearTemplateCache($template->tenant_id);
            
            return $template->fresh();
        });
    }

    /**
     * Duplicate template
     */
    public function duplicateTemplate(FormTemplate $template, array $changes = []): FormTemplate
    {
        return DB::transaction(function () use ($template, $changes) {
            $duplicated = $template->duplicate($changes);
            
            // Create initial version for duplicated template
            $duplicated->createVersion('Duplicated from: ' . $template->name, auth()->id());
            
            $this->clearTemplateCache($duplicated->tenant_id);
            
            return $duplicated;
        });
    }

    /**
     * Get methodology-specific templates
     */
    public function getMethodologyTemplates(string $methodology): Collection
    {
        $cacheKey = "methodology_templates_{$methodology}";
        
        return Cache::remember($cacheKey, 7200, function () use ($methodology) {
            return FormTemplate::where('methodology_type', $methodology)
                ->where('is_default', true)
                ->active()
                ->get();
        });
    }

    /**
     * Generate template from project data
     */
    public function generateTemplateFromProject(int $projectId, string $templateName): FormTemplate
    {
        $project = \App\Models\Projects\Project::with('formInstance.template')->findOrFail($projectId);
        
        if (!$project->formInstance) {
            throw new \Exception('Project does not have associated form data');
        }
        
        $baseTemplate = $project->formInstance->template;
        $projectData = $project->formInstance->form_data;
        
        // Create template with pre-populated default values from project
        $templateData = $baseTemplate->toArray();
        unset($templateData['id'], $templateData['created_at'], $templateData['updated_at']);
        
        $templateData['name'] = $templateName;
        $templateData['is_default'] = false;
        $templateData['version'] = '1.0';
        
        // Add default values from successful project
        $templateData['default_values'] = $this->extractDefaultValues($projectData, $baseTemplate);
        
        return $this->createTemplate($project->tenant_id, $templateData);
    }

    /**
     * Validate template structure
     */
    private function validateTemplateStructure(array $templateData): void
    {
        $required = ['name', 'category', 'steps'];
        
        foreach ($required as $field) {
            if (empty($templateData[$field])) {
                throw new \InvalidArgumentException("Template field '{$field}' is required");
            }
        }
        
        // Validate steps structure
        if (!is_array($templateData['steps']) || empty($templateData['steps'])) {
            throw new \InvalidArgumentException('Template must have at least one step');
        }
        
        foreach ($templateData['steps'] as $stepIndex => $step) {
            $this->validateStepStructure($step, $stepIndex);
        }
    }

    /**
     * Validate individual step structure
     */
    private function validateStepStructure(array $step, int $stepIndex): void
    {
        $required = ['step_id', 'step_title', 'sections'];
        
        foreach ($required as $field) {
            if (empty($step[$field])) {
                throw new \InvalidArgumentException("Step {$stepIndex}: field '{$field}' is required");
            }
        }
        
        if (!is_array($step['sections']) || empty($step['sections'])) {
            throw new \InvalidArgumentException("Step {$stepIndex}: must have at least one section");
        }
        
        foreach ($step['sections'] as $sectionIndex => $section) {
            $this->validateSectionStructure($section, $stepIndex, $sectionIndex);
        }
    }

    /**
     * Validate section structure
     */
    private function validateSectionStructure(array $section, int $stepIndex, int $sectionIndex): void
    {
        $required = ['section_id', 'section_title', 'fields'];
        
        foreach ($required as $field) {
            if (empty($section[$field])) {
                throw new \InvalidArgumentException("Step {$stepIndex}, Section {$sectionIndex}: field '{$field}' is required");
            }
        }
        
        if (!is_array($section['fields'])) {
            throw new \InvalidArgumentException("Step {$stepIndex}, Section {$sectionIndex}: fields must be an array");
        }
        
        foreach ($section['fields'] as $fieldIndex => $field) {
            $this->validateFieldStructure($field, $stepIndex, $sectionIndex, $fieldIndex);
        }
    }

    /**
     * Validate field structure
     */
    private function validateFieldStructure(array $field, int $stepIndex, int $sectionIndex, int $fieldIndex): void
    {
        $required = ['field_id', 'field_type', 'label'];
        
        foreach ($required as $fieldName) {
            if (empty($field[$fieldName])) {
                throw new \InvalidArgumentException(
                    "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: '{$fieldName}' is required"
                );
            }
        }
        
        // Validate field type
        $validTypes = [
            'text', 'textarea', 'email', 'password', 'number', 'currency',
            'date', 'daterange', 'time', 'datetime',
            'dropdown', 'multiselect', 'radio', 'checkbox', 'checkbox_group',
            'user_select', 'multi_user_select', 'file_upload', 'image_upload',
            'datatable', 'repeater', 'panel', 'alert', 'summary_panel',
            'rating', 'slider', 'toggle', 'chips', 'autocomplete',
            'rich_text', 'code_editor', 'map_location', 'signature',
            'calculated_field', 'hidden', 'display_only'
        ];
        
        if (!in_array($field['field_type'], $validTypes)) {
            throw new \InvalidArgumentException(
                "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: invalid field type '{$field['field_type']}'"
            );
        }
    }

    /**
     * Check if template changes are significant enough to warrant version creation
     */
    private function hasSignificantChanges(array $original, array $updated): bool
    {
        $significantFields = ['steps', 'validation_rules', 'workflow_configuration', 'methodology_type'];
        
        foreach ($significantFields as $field) {
            if (($original[$field] ?? null) !== ($updated[$field] ?? null)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate summary of changes between versions
     */
    private function generateChangesSummary(array $original, array $updated): string
    {
        $changes = [];
        
        if (($original['name'] ?? '') !== ($updated['name'] ?? '')) {
            $changes[] = 'Name updated';
        }
        
        if (($original['methodology_type'] ?? '') !== ($updated['methodology_type'] ?? '')) {
            $changes[] = 'Methodology changed to ' . ($updated['methodology_type'] ?? 'universal');
        }
        
        if (count($original['steps'] ?? []) !== count($updated['steps'] ?? [])) {
            $changes[] = 'Steps modified';
        }
        
        if (($original['validation_rules'] ?? []) !== ($updated['validation_rules'] ?? [])) {
            $changes[] = 'Validation rules updated';
        }
        
        if (($original['workflow_configuration'] ?? []) !== ($updated['workflow_configuration'] ?? [])) {
            $changes[] = 'Workflow configuration updated';
        }
        
        return empty($changes) ? 'Template updated' : implode(', ', $changes);
    }

    /**
     * Extract default values from successful project data
     */
    private function extractDefaultValues(array $projectData, FormTemplate $template): array
    {
        $defaults = [];
        $fields = $template->getAllFields();
        
        foreach ($fields as $fieldId => $field) {
            $fieldType = $field['field_type'] ?? '';
            $value = $projectData[$fieldId] ?? null;
            
            // Only extract certain types of values as defaults
            if ($value !== null && $this->shouldUseAsDefault($fieldType, $value)) {
                $defaults[$fieldId] = $value;
            }
        }
        
        return $defaults;
    }

    /**
     * Determine if a field value should be used as default
     */
    private function shouldUseAsDefault(string $fieldType, $value): bool
    {
        // Don't use personal/unique data as defaults
        $excludeTypes = ['email', 'signature', 'file_upload', 'image_upload'];
        
        if (in_array($fieldType, $excludeTypes)) {
            return false;
        }
        
        // Don't use dates as defaults
        if (in_array($fieldType, ['date', 'daterange', 'datetime'])) {
            return false;
        }
        
        // Only use dropdown/select values if they're likely to be reusable
        if (in_array($fieldType, ['dropdown', 'radio']) && is_string($value)) {
            return !$this->isPersonalData($value);
        }
        
        return true;
    }

    /**
     * Check if value appears to be personal data
     */
    private function isPersonalData(string $value): bool
    {
        // Simple heuristics to detect personal data
        $personalPatterns = [
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN-like patterns
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
            '/\b\d{10,}\b/', // Long numbers (phone, ID, etc.)
        ];
        
        foreach ($personalPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear template cache for tenant
     */
    private function clearTemplateCache(int $tenantId): void
    {
        $keys = [
            "org_templates_{$tenantId}_*",
            "form_template_*"
        ];
        
        foreach ($keys as $pattern) {
            Cache::flush(); // In production, use more specific cache clearing
        }
    }
}

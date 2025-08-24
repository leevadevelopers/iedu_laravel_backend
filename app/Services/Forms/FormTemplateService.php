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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FormTemplateService
{
    /**
     * Get templates for organization with caching
     */
    public function getOrgTemplates(int $tenantId, ?string $category = null): Collection
    {
        $cacheKey = "org_templates_{$tenantId}_{$category}";

        // Track this cache key for later clearing
        $this->trackCacheKey($tenantId, $cacheKey);

        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $category) {
            $query = FormTemplate::where('tenant_id', $tenantId)
                ->nonDeleted() // Use nonDeleted scope instead of active to avoid conflicts
                ->where('is_active', true) // Explicitly check is_active
                ->orderBy('is_default', 'desc')
                ->orderBy('name');

            if ($category) {
                $query->where('category', $category);
            }

            $templates = $query->with('creator')->get();

            // Debug logging to check what's being returned
            Log::info("FormTemplateService::getOrgTemplates", [
                'tenant_id' => $tenantId,
                'category' => $category,
                'total_templates' => $templates->count(),
                'templates_with_deleted_at' => $templates->filter(fn($t) => !is_null($t->deleted_at))->count(),
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            return $templates;
        });
    }

    /**
     * Track cache keys for proper cache management
     */
    private function trackCacheKey(int $tenantId, string $cacheKey): void
    {
        $existingKeys = Cache::get('template_cache_keys_' . $tenantId, []);
        if (!in_array($cacheKey, $existingKeys)) {
            $existingKeys[] = $cacheKey;
            Cache::put('template_cache_keys_' . $tenantId, $existingKeys, 86400); // 24 hours
        }
    }

    /**
     * Load template with full configuration
     */
    public function loadTemplate(int $templateId): FormTemplate
    {
        $cacheKey = "form_template_{$templateId}";

        // Track this cache key for later clearing
        $this->trackCacheKey(1, $cacheKey); // Default tenant ID for individual templates

        return Cache::remember($cacheKey, 3600, function () use ($templateId) {
            return FormTemplate::with(['creator', 'versions'])
                ->findOrFail($templateId);
        });
    }

    /**
     * Get deleted templates for admin purposes
     */
    public function getDeletedTemplates(int $tenantId, ?string $category = null): Collection
    {
        $cacheKey = "deleted_templates_{$tenantId}_{$category}";

        // Track this cache key for later clearing
        $this->trackCacheKey($tenantId, $cacheKey);

        return Cache::remember($cacheKey, 1800, function () use ($tenantId, $category) { // 30 minutes cache
            $query = FormTemplate::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->orderBy('deleted_at', 'desc');

            if ($category) {
                $query->where('category', $category);
            }

            return $query->with('creator')->get();
        });
    }

    /**
     * Get count of deleted templates for a tenant
     */
    public function getDeletedTemplatesCount(int $tenantId): int
    {
        $cacheKey = "deleted_templates_count_{$tenantId}";

        return Cache::remember($cacheKey, 1800, function () use ($tenantId) { // 30 minutes cache
            return FormTemplate::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->count();
        });
    }

    /**
     * Restore a soft-deleted template
     */
    public function restoreTemplate(int $templateId): FormTemplate
    {
        $template = FormTemplate::onlyTrashed()->findOrFail($templateId);

        if (!$template->trashed()) {
            throw new \Exception('Template is not deleted');
        }

        $template->restore();

        // Clear template cache
        $this->clearTemplateCache($template->tenant_id);

        return $template;
    }

    /**
     * Permanently delete a template (force delete)
     */
    public function forceDeleteTemplate(int $templateId): bool
    {
        $template = FormTemplate::withTrashed()->findOrFail($templateId);

        return DB::transaction(function () use ($template) {
            // Check if template is in use
            if ($template->instances()->exists()) {
                throw new \Exception('Cannot permanently delete template that has form instances');
            }

            // Delete all versions first
            $template->versions()->delete();

            // Force delete the template
            $deleted = $template->forceDelete();

            // Clear template cache
            $this->clearTemplateCache($template->tenant_id);

            return $deleted;
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

            // Ensure default values
            $templateData = array_merge([
                'tenant_id' => $tenantId,
                'version' => '1.0',
                'is_active' => true,
                'is_default' => false,
                'auto_save' => true,
                'compliance_level' => 'standard',
                'form_configuration' => [
                    'layout' => 'vertical',
                    'theme' => 'default',
                    'show_progress' => true,
                    'allow_save_draft' => true,
                    'allow_preview' => false
                ],
                'created_by' => auth('api')->id(),
            ], $templateData);

            $template = FormTemplate::create($templateData);

            // Create initial version
            $template->createVersion('Initial template creation', auth('api')->id());

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
            $templateData['created_by'] = auth('api')->id();

            $customizedTemplate = FormTemplate::create($templateData);

            // Create version tracking
            $customizedTemplate->createVersion('Customized from template: ' . $baseTemplate->name, auth('api')->id());

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

            $template->update($updates);

            // Create version if significant changes
            if ($this->hasSignificantChanges($originalData, $template->toArray())) {
                $changesSummary = $this->generateChangesSummary($originalData, $template->toArray());
                $template->createVersion($changesSummary, auth('api')->id());
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
            $duplicated->createVersion('Duplicated from: ' . $template->name, auth('api')->id());

            $this->clearTemplateCache($duplicated->tenant_id);

            return $duplicated;
        });
    }

    /**
     * Generate template from project data
     */
    // public function generateTemplateFromProject(int $projectId, string $templateName): FormTemplate
    // {
    //     $project = \App\Models\Project\Project::with('formInstance.template')->findOrFail($projectId);

    //     if (!$project->formInstance) {
    //         throw new \Exception('Project does not have associated form data');
    //     }

    //     $baseTemplate = $project->formInstance->template;
    //     $projectData = $project->formInstance->form_data;

    //     // Create template with pre-populated default values from project
    //     $templateData = $baseTemplate->toArray();
    //     unset($templateData['id'], $templateData['created_at'], $templateData['updated_at']);

    //     $templateData['name'] = $templateName;
    //     $templateData['is_default'] = false;
    //     $templateData['version'] = '1.0';

    //     // Add default values from successful project
    //     $templateData['default_values'] = $this->extractDefaultValues($projectData, $baseTemplate);

    //     return $this->createTemplate($project->tenant_id, $templateData);
    // }

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
            // Basic Input Fields
            'text', 'textarea', 'email', 'password', 'number', 'currency', 'phone', 'tel', 'url',
            // Date & Time Fields
            'date', 'daterange', 'time', 'datetime', 'datetime-local', 'month', 'week',
            // Selection Fields
            'dropdown', 'multiselect', 'radio', 'checkbox', 'checkbox_group', 'select', 'switch',
            // User Selection Fields
            'user_select', 'multi_user_select',
            // File Fields
            'file_upload', 'image_upload', 'file', 'image',
            // Advanced Fields
            'datatable', 'repeater', 'panel', 'alert', 'summary_panel', 'dynamic-list',
            // UI Fields
            'rating', 'slider', 'toggle', 'chips', 'autocomplete', 'range', 'percentage',
            // Content Fields
            'rich_text', 'code_editor', 'map_location', 'signature', 'wysiwyg', 'location',
            // Special Fields
            'calculated_field', 'hidden', 'display_only', 'relationship'
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
        $significantFields = ['steps', 'validation_rules', 'workflow_configuration'];

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
        // Clear specific template caches instead of flushing all
        $patterns = [
            "org_templates_{$tenantId}_*",
            "form_template_*",
            "deleted_templates_{$tenantId}_*",
            "deleted_templates_count_{$tenantId}"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we need to get all matching keys
                $keys = Cache::get('template_cache_keys_' . $tenantId, []);
                foreach ($keys as $key) {
                    if (str_starts_with($key, str_replace('*', '', $pattern))) {
                        Cache::forget($key);
                    }
                }
            } else {
                Cache::forget($pattern);
            }
        }

        // Clear the cache keys registry
        Cache::forget('template_cache_keys_' . $tenantId);
    }

    /**
     * Clear all template caches immediately (for debugging)
     */
    public function clearAllTemplateCaches(): void
    {
        // Clear all possible cache patterns
        $patterns = [
            "org_templates_*",
            "form_template_*",
            "deleted_templates_*",
            "deleted_templates_count_*"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, try to clear common keys
                for ($i = 1; $i <= 10; $i++) { // Clear first 10 tenants
                    Cache::forget(str_replace('*', $i, $pattern));
                }
            } else {
                Cache::forget($pattern);
            }
        }

        Log::info("All template caches cleared");
    }
}

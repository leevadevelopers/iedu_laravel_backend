<?php 
// File: app/Services/Forms/FormPatternEngine.php
namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Support\Facades\DB;

class FormPatternEngine
{
    /**
     * Generate suggestions based on historical patterns
     */
    public function generateSuggestions(string $fieldId, array $context): array
    {
        $suggestions = [];
        
        // Get historical data for this field type
        $historicalData = $this->getHistoricalFieldData($fieldId, $context);
        
        // Pattern-based suggestions
        if ($fieldId === 'project_name') {
            $suggestions = $this->suggestProjectNames($context);
        } elseif ($fieldId === 'budget') {
            $suggestions = $this->suggestBudgetRanges($context);
        } elseif (str_ends_with($fieldId, '_location')) {
            $suggestions = $this->suggestLocations($context);
        } elseif (str_ends_with($fieldId, '_duration')) {
            $suggestions = $this->suggestDurations($context);
        }
        
        // Add frequent values for this field
        $frequentValues = $this->getFrequentFieldValues($fieldId, $context);
        $suggestions = array_merge($suggestions, $frequentValues);
        
        return array_unique($suggestions);
    }

    /**
     * Auto-populate fields based on organizational patterns
     */
    public function autoPopulateFields(FormTemplate $template, array $context): array
    {
        $populatedData = [];
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return $populatedData;
        }
        
        // Get organizational defaults
        $orgDefaults = $this->getOrganizationalDefaults($tenantId, $template->category);
        
        foreach ($template->getAllFields() as $fieldId => $field) {
            if (isset($orgDefaults[$fieldId])) {
                $populatedData[$fieldId] = $orgDefaults[$fieldId];
            }
            
            // Pattern-based auto-population
            if ($field['field_type'] === 'currency' && !isset($populatedData[$fieldId])) {
                $populatedData[$fieldId] = $this->getDefaultCurrency($tenantId);
            }
            
            if ($field['field_type'] === 'user_select' && str_contains($fieldId, 'manager')) {
                $populatedData[$fieldId] = $this->getDefaultManager($tenantId, $context);
            }
        }
        
        return $populatedData;
    }

    private function suggestProjectNames(array $context): array
    {
        $category = $context['category'] ?? '';
        $patterns = [
            'health' => ['Health Initiative', 'Medical Support Program', 'Healthcare Development'],
            'education' => ['Education Enhancement', 'Learning Support Initiative', 'Educational Development'],
            'infrastructure' => ['Infrastructure Development', 'Construction Project', 'Facility Improvement'],
            'agriculture' => ['Agricultural Development', 'Farming Support Program', 'Rural Development']
        ];
        
        return $patterns[$category] ?? ['Development Initiative', 'Support Program', 'Community Project'];
    }

    private function suggestBudgetRanges(array $context): array
    {
        $category = $context['category'] ?? '';
        $ranges = [
            'health' => [50000, 100000, 250000, 500000, 1000000],
            'education' => [25000, 75000, 150000, 300000, 600000],
            'infrastructure' => [100000, 500000, 1000000, 2000000, 5000000],
            'agriculture' => [30000, 80000, 200000, 400000, 800000]
        ];
        
        return $ranges[$category] ?? [50000, 100000, 250000, 500000];
    }

    private function suggestLocations(array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        // Get frequently used locations for this tenant
        return DB::table('form_instances')
            ->join('form_templates', 'form_instances.form_template_id', '=', 'form_templates.id')
            ->where('form_templates.tenant_id', $tenantId)
            ->whereNotNull('form_instances.form_data->location')
            ->groupBy('form_instances.form_data->location')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->pluck('form_instances.form_data->location')
            ->toArray();
    }

    private function suggestDurations(array $context): array
    {
        $category = $context['category'] ?? '';
        $durations = [
            'health' => ['6 months', '12 months', '18 months', '24 months'],
            'education' => ['3 months', '6 months', '12 months', '36 months'],
            'infrastructure' => ['12 months', '18 months', '24 months', '36 months'],
            'agriculture' => ['6 months', '12 months', '24 months', '36 months']
        ];
        
        return $durations[$category] ?? ['6 months', '12 months', '18 months', '24 months'];
    }

    private function getHistoricalFieldData(string $fieldId, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        return DB::table('form_instances')
            ->join('form_templates', 'form_instances.form_template_id', '=', 'form_templates.id')
            ->where('form_templates.tenant_id', $tenantId)
            ->whereNotNull("form_instances.form_data->{$fieldId}")
            ->orderBy('form_instances.created_at', 'desc')
            ->limit(100)
            ->pluck("form_instances.form_data->{$fieldId}")
            ->toArray();
    }

    private function getFrequentFieldValues(string $fieldId, array $context): array
    {
        $tenantId = $context['tenant_id'] ?? null;
        
        if (!$tenantId) {
            return [];
        }
        
        return DB::table('form_instances')
            ->join('form_templates', 'form_instances.form_template_id', '=', 'form_templates.id')
            ->where('form_templates.tenant_id', $tenantId)
            ->whereNotNull("form_instances.form_data->{$fieldId}")
            ->groupBy("form_instances.form_data->{$fieldId}")
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(5)
            ->pluck("form_instances.form_data->{$fieldId}")
            ->toArray();
    }

    private function getOrganizationalDefaults(int $tenantId, string $category): array
    {
        // This would typically come from tenant settings or configuration
        return DB::table('tenant_form_defaults')
            ->where('tenant_id', $tenantId)
            ->where('category', $category)
            ->first()?->defaults ?? [];
    }

    private function getDefaultCurrency(int $tenantId): string
    {
        // Get from tenant settings
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->value('settings->default_currency') ?? 'USD';
    }

    private function getDefaultManager(int $tenantId, array $context): ?int
    {
        // Get the most common project manager for this tenant
        return DB::table('form_instances')
            ->join('form_templates', 'form_instances.form_template_id', '=', 'form_templates.id')
            ->where('form_templates.tenant_id', $tenantId)
            ->whereNotNull('form_instances.form_data->project_manager')
            ->groupBy('form_instances.form_data->project_manager')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(1)
            ->value('form_instances.form_data->project_manager');
    }
}

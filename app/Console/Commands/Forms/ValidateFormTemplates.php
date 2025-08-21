<?php 
// File: app/Console/Commands/Forms/ValidateFormTemplates.php
namespace App\Console\Commands\Forms;

use App\Models\Forms\FormTemplate;
use App\Services\Forms\FormTemplateService;
use Illuminate\Console\Command;

class ValidateFormTemplates extends Command
{
    protected $signature = 'forms:validate-templates 
                            {--tenant= : Validate templates for specific tenant}
                            {--fix : Attempt to fix validation issues}';

    protected $description = 'Validate form template structures and integrity';

    protected $templateService;

    public function __construct(FormTemplateService $templateService)
    {
        parent::__construct();
        $this->templateService = $templateService;
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $fix = $this->option('fix');
        
        $query = FormTemplate::query();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $templates = $query->get();
        $this->info("Validating {$templates->count()} form templates...");
        
        $issues = [];
        $progressBar = $this->output->createProgressBar($templates->count());
        
        foreach ($templates as $template) {
            $templateIssues = $this->validateTemplate($template, $fix);
            if (!empty($templateIssues)) {
                $issues[$template->id] = [
                    'template' => $template,
                    'issues' => $templateIssues
                ];
            }
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        if (empty($issues)) {
            $this->info('✅ All form templates are valid!');
            return 0;
        }
        
        $this->error("❌ Found issues in " . count($issues) . " templates:");
        
        foreach ($issues as $templateId => $data) {
            $template = $data['template'];
            $templateIssues = $data['issues'];
            
            $this->newLine();
            $this->line("<fg=yellow>Template: {$template->name} (ID: {$templateId})</>");
            
            foreach ($templateIssues as $issue) {
                $this->line("  - {$issue}");
            }
        }
        
        return count($issues) > 0 ? 1 : 0;
    }

    private function validateTemplate(FormTemplate $template, bool $fix = false): array
    {
        $issues = [];
        
        try {
            // Validate basic structure
            if (empty($template->steps)) {
                $issues[] = 'Template has no steps defined';
            }
            
            if (empty($template->name)) {
                $issues[] = 'Template has no name';
            }
            
            // Validate steps structure
            foreach ($template->steps as $stepIndex => $step) {
                $stepIssues = $this->validateStep($step, $stepIndex);
                $issues = array_merge($issues, $stepIssues);
            }
            
            // Validate methodology compliance
            if ($template->methodology_type !== 'universal') {
                $complianceIssues = $this->validateMethodologyCompliance($template);
                $issues = array_merge($issues, $complianceIssues);
            }
            
            // Validate workflow configuration
            if ($template->workflow_configuration) {
                $workflowIssues = $this->validateWorkflowConfiguration($template->workflow_configuration);
                $issues = array_merge($issues, $workflowIssues);
            }
            
        } catch (\Exception $e) {
            $issues[] = "Validation error: " . $e->getMessage();
        }
        
        return $issues;
    }

    private function validateStep(array $step, int $stepIndex): array
    {
        $issues = [];
        
        if (empty($step['step_id'])) {
            $issues[] = "Step {$stepIndex}: Missing step_id";
        }
        
        if (empty($step['step_title'])) {
            $issues[] = "Step {$stepIndex}: Missing step_title";
        }
        
        if (empty($step['sections']) || !is_array($step['sections'])) {
            $issues[] = "Step {$stepIndex}: Missing or invalid sections";
        } else {
            foreach ($step['sections'] as $sectionIndex => $section) {
                $sectionIssues = $this->validateSection($section, $stepIndex, $sectionIndex);
                $issues = array_merge($issues, $sectionIssues);
            }
        }
        
        return $issues;
    }

    private function validateSection(array $section, int $stepIndex, int $sectionIndex): array
    {
        $issues = [];
        
        if (empty($section['section_id'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}: Missing section_id";
        }
        
        if (empty($section['section_title'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}: Missing section_title";
        }
        
        if (!isset($section['fields']) || !is_array($section['fields'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}: Missing or invalid fields";
        } else {
            foreach ($section['fields'] as $fieldIndex => $field) {
                $fieldIssues = $this->validateField($field, $stepIndex, $sectionIndex, $fieldIndex);
                $issues = array_merge($issues, $fieldIssues);
            }
        }
        
        return $issues;
    }

    private function validateField(array $field, int $stepIndex, int $sectionIndex, int $fieldIndex): array
    {
        $issues = [];
        
        if (empty($field['field_id'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: Missing field_id";
        }
        
        if (empty($field['field_type'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: Missing field_type";
        }
        
        if (empty($field['label'])) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: Missing label";
        }
        
        // Validate field type
        $validTypes = config('form_engine.field_types', []);
        if (!empty($field['field_type']) && !array_key_exists($field['field_type'], $validTypes)) {
            $issues[] = "Step {$stepIndex}, Section {$sectionIndex}, Field {$fieldIndex}: Invalid field_type '{$field['field_type']}'";
        }
        
        return $issues;
    }

    private function validateMethodologyCompliance(FormTemplate $template): array
    {
        $issues = [];
        $methodology = $template->methodology_type;
        $requiredFields = config("form_engine.methodologies.{$methodology}.required_fields", []);
        
        if (empty($requiredFields)) {
            return $issues;
        }
        
        $allFields = $template->getAllFields();
        $fieldIds = array_keys($allFields);
        
        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField, $fieldIds)) {
                $issues[] = "Missing required field for {$methodology} methodology: {$requiredField}";
            }
        }
        
        return $issues;
    }

    private function validateWorkflowConfiguration(array $workflowConfig): array
    {
        $issues = [];
        
        if (empty($workflowConfig['steps'])) {
            $issues[] = 'Workflow configuration missing steps';
        }
        
        foreach ($workflowConfig['steps'] ?? [] as $stepIndex => $step) {
            if (empty($step['step_name'])) {
                $issues[] = "Workflow step {$stepIndex}: Missing step_name";
            }
            
            if (empty($step['approver_role']) && empty($step['required_permissions'])) {
                $issues[] = "Workflow step {$stepIndex}: No approver role or permissions defined";
            }
        }
        
        return $issues;
    }
}
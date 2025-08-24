<?php

// File: app/Http/Requests/Forms/UpdateFormTemplateRequest.php
namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');
        // return auth()->user()->hasTenantPermission('forms.edit_template') ||
        //        $template->created_by === auth()->id();
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|in:project_creation,contract_management,procurement,monitoring,financial,custom,project,planning,execution,closure,risk_assessment,risk_mitigation,risk_monitoring,risk_report,indicator,evaluation,monitoring_report,dashboard,budget,transaction,expense,financial_report,audit,procurement_request,tender,contract,procurement_evaluation,supplier',
            'estimated_completion_time' => 'nullable|string|max:50',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'compliance_level' => 'nullable|string|in:basic,standard,strict,custom',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
            'form_configuration' => 'nullable|array',
            'steps' => 'nullable|array|min:1',
            'steps.*.step_id' => 'required_with:steps|string',
            'steps.*.step_title' => 'required_with:steps|string',
            'steps.*.sections' => 'required_with:steps|array|min:1',
            'steps.*.sections.*.section_id' => 'required_with:steps|string',
            'steps.*.sections.*.section_title' => 'required_with:steps|string',
            'steps.*.sections.*.fields' => 'required_with:steps|array',
            'steps.*.sections.*.fields.*.field_id' => 'required_with:steps|string',
            'steps.*.sections.*.fields.*.field_type' => 'required_with:steps|string',
            'steps.*.sections.*.fields.*.label' => 'required_with:steps|string',
            'form_triggers' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'ai_prompts' => 'nullable|array'
        ];
    }
}

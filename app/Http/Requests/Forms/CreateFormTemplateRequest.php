<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class CreateFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // return auth()->user()->hasTenantPermission('forms.create_template');
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:project_creation,contract_management,procurement,monitoring,financial,custom,project,planning,execution,closure,risk_assessment,risk_mitigation,risk_monitoring,risk_report,indicator,evaluation,monitoring_report,dashboard,budget,transaction,expense,financial_report,audit,procurement_request,tender,contract,procurement_evaluation,supplier',
            'estimated_completion_time' => 'nullable|string|max:50',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'compliance_level' => 'nullable|string|in:basic,standard,strict,custom',
            'is_default' => 'boolean',
            'metadata' => 'nullable|array',
            'form_configuration' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.step_id' => 'required|string',
            'steps.*.step_title' => 'required|string',
            'steps.*.sections' => 'required|array|min:1',
            'steps.*.sections.*.section_id' => 'required|string',
            'steps.*.sections.*.section_title' => 'required|string',
            'steps.*.sections.*.fields' => 'required|array',
            'steps.*.sections.*.fields.*.field_id' => 'required|string',
            'steps.*.sections.*.fields.*.field_type' => 'required|string',
            'steps.*.sections.*.fields.*.label' => 'required|string',
            'form_triggers' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'ai_prompts' => 'nullable|array'
        ];
    }

    public function messages(): array
    {
        return [
            'steps.required' => 'Form template must have at least one step',
            'steps.*.sections.required' => 'Each step must have at least one section',
            'steps.*.sections.*.fields.required' => 'Each section must have at least one field'
        ];
    }
}

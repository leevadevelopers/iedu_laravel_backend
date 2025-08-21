<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');
        return auth()->user()->hasTenantPermission('forms.edit_template') || 
               $template->created_by === auth()->id();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|string|in:project_creation,contract_management,procurement,monitoring,financial,custom',
            'methodology_type' => 'nullable|string|in:universal,usaid,world_bank,eu,custom',
            'estimated_completion_time' => 'nullable|string|max:50',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'compliance_level' => 'string|in:basic,standard,strict,custom',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'metadata' => 'nullable|array',
            'form_configuration' => 'sometimes|array',
            'steps' => 'sometimes|array|min:1',
            'form_triggers' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'ai_prompts' => 'nullable|array'
        ];
    }
}

<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|required|string|max:100',
            'compliance_level' => 'sometimes|required|in:basic,standard,strict,comprehensive',
            'form_configuration' => 'sometimes|required|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'estimated_completion_time' => 'nullable|integer|min:1',
            'tags' => 'nullable|array',
            'is_active' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'name.max' => 'Template name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'category.required' => 'Category is required.',
            'category.max' => 'Category cannot exceed 100 characters.',
            'compliance_level.required' => 'Compliance level is required.',
            'compliance_level.in' => 'Invalid compliance level selected.',
            'form_configuration.required' => 'Form configuration is required.',
            'form_configuration.array' => 'Form configuration must be an array.',
            'validation_rules.array' => 'Validation rules must be an array.',
            'workflow_configuration.array' => 'Workflow configuration must be an array.',
            'is_multi_step.boolean' => 'Multi-step must be a boolean value.',
            'auto_save.boolean' => 'Auto-save must be a boolean value.',
            'estimated_completion_time.integer' => 'Estimated completion time must be an integer.',
            'estimated_completion_time.min' => 'Estimated completion time must be at least 1 minute.',
            'tags.array' => 'Tags must be an array.',
            'is_active.boolean' => 'Active status must be a boolean value.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'template name',
            'description' => 'description',
            'category' => 'category',
            'compliance_level' => 'compliance level',
            'form_configuration' => 'form configuration',
            'validation_rules' => 'validation rules',
            'workflow_configuration' => 'workflow configuration',
            'is_multi_step' => 'multi-step',
            'auto_save' => 'auto-save',
            'estimated_completion_time' => 'estimated completion time',
            'tags' => 'tags',
            'is_active' => 'active status',
        ];
    }
}

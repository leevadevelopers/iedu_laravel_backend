<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateFormTemplateRequest extends FormRequest
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
            'tenant_id' => 'required|integer|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'customizations' => 'nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Tenant ID is required.',
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'name.required' => 'Template name is required.',
            'name.max' => 'Template name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'customizations.array' => 'Customizations must be an array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => 'tenant',
            'name' => 'template name',
            'description' => 'description',
            'customizations' => 'customizations',
        ];
    }
}

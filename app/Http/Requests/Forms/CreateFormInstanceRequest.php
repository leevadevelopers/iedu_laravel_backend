<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class CreateFormInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // return auth()->user()->hasTenantPermission('forms.create');
        return true;
    }

    public function rules(): array
    {
        return [
            'form_template_id' => 'required|exists:form_templates,id',
            'form_data' => 'nullable|array',
            'auto_populate' => 'boolean',
            'context' => 'nullable|array'
        ];
    }
}

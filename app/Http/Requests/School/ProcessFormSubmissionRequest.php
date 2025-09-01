<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class ProcessFormSubmissionRequest extends FormRequest
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
            'form_template_id' => 'required|exists:form_templates,id',
            'form_data' => 'required|array',
            'submission_type' => 'nullable|string|max:100',
            'metadata' => 'nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'form_template_id.required' => 'Form template ID is required.',
            'form_template_id.exists' => 'The selected form template does not exist.',
            'form_data.required' => 'Form data is required.',
            'form_data.array' => 'Form data must be an array.',
            'submission_type.max' => 'Submission type cannot exceed 100 characters.',
            'metadata.array' => 'Metadata must be an array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'form_template_id' => 'form template',
            'form_data' => 'form data',
            'submission_type' => 'submission type',
            'metadata' => 'metadata',
        ];
    }
}

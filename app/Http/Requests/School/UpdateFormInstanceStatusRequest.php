<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormInstanceStatusRequest extends FormRequest
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
            'status' => 'required|in:draft,submitted,under_review,approved,rejected,completed',
            'notes' => 'nullable|string|max:1000',
            'workflow_state' => 'nullable|string|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status selected. Allowed values: draft, submitted, under_review, approved, rejected, completed.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'workflow_state.max' => 'Workflow state cannot exceed 100 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'status' => 'status',
            'notes' => 'notes',
            'workflow_state' => 'workflow state',
        ];
    }
}

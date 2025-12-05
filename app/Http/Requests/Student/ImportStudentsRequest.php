<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Import Students Request
 * 
 * Validates CSV file upload for bulk student import
 */
class ImportStudentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240' // 10MB
            ],
            'skip_duplicates' => 'nullable|boolean',
            'update_existing' => 'nullable|boolean',
            'validate_only' => 'nullable|boolean',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'school_id' => 'nullable|integer|exists:schools,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'CSV file is required',
            'file.file' => 'Uploaded file must be a valid file',
            'file.mimes' => 'File must be a CSV file',
            'file.max' => 'File size must not exceed 10MB',
            'tenant_id.exists' => 'Selected tenant does not exist',
            'school_id.exists' => 'Selected school does not exist'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'CSV file',
            'skip_duplicates' => 'skip duplicates option',
            'update_existing' => 'update existing option',
            'validate_only' => 'validate only option'
        ];
    }
}



<?php

namespace App\Http\Requests\Communication;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class SendSMSRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient' => 'required|string|max:20',
            'message' => 'required|string|max:1000',
            'template_id' => 'nullable|string|max:100',
            'scheduled_at' => 'nullable|date|after:now',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Remove tenant_id and school_id if provided (set automatically)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}


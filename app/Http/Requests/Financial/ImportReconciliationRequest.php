<?php

namespace App\Http\Requests\Financial;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class ImportReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB
            'provider' => 'required|in:mpesa,emola,other',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
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


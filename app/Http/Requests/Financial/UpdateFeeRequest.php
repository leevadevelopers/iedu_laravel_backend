<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'recurring' => 'boolean',
            'frequency' => 'nullable|in:monthly,quarterly,semestral,annual',
            'applied_to' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Remover tenant_id e school_id se forem enviados (são definidos automaticamente)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}

<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'recurring' => 'boolean',
            'frequency' => 'nullable|in:monthly,quarterly,semestral,annual',
            'applied_to' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }
}

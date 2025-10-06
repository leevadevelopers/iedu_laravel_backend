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
            'code' => "sometimes|required|string|unique:fees,code",
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'recurring' => 'boolean',
            'frequency' => 'nullable|in:monthly,quarterly,semestral,annual',
            'applied_to' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }
}

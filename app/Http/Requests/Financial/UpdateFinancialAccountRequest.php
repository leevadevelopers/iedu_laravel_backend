<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:financial_accounts,code,' . ($account?->id ?? 'NULL'),
            'type' => 'sometimes|required|in:asset,liability,equity,revenue,expense',
            'balance' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ];
    }
}

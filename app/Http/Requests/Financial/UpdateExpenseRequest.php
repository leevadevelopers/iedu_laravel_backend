<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => 'sometimes|required|exists:financial_accounts,id',
            'category' => 'sometimes|required|string|max:100',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|required|string',
            'incurred_at' => 'sometimes|required|date',
            'receipt_path' => 'nullable|string',
        ];
    }
}

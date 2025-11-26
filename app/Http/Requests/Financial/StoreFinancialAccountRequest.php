<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:financial_accounts,code',
            'type' => 'nullable|in:asset,liability,equity,revenue,expense,bank,cash,credit,savings,investment,other',
            'account_type' => 'nullable|in:bank,cash,credit,savings,investment,other,asset,liability,equity,revenue,expense',
            'account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_branch' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3|default:BRL',
            'balance' => 'nullable|numeric|min:0',
            'initial_balance' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive,closed',
            'description' => 'nullable|string',
            'tenant_id' => 'nullable|exists:tenants,id',
            'school_id' => 'nullable|exists:schools,id',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        // Mapear account_type para type se necessário
        if ($this->has('account_type') && !$this->has('type')) {
            $this->merge(['type' => $this->input('account_type')]);
        }
        
        // Mapear status para is_active se necessário
        if ($this->has('status') && !$this->has('is_active')) {
            $this->merge(['is_active' => $this->input('status') === 'active']);
        }
    }
}

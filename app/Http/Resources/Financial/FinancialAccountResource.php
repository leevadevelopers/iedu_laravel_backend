<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'account_type' => $this->type, // Alias para compatibilidade com frontend
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'currency' => $this->currency ?? 'BRL',
            'balance' => (string) $this->balance,
            'initial_balance' => $this->initial_balance ? (string) $this->initial_balance : null,
            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive', // Mapear is_active para status
            'description' => $this->description,
            'transactions_count' => $this->whenCounted('transactions'),
            'expenses_count' => $this->whenCounted('expenses'),
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}

<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category' => $this->category,
            'amount' => $this->amount,
            'description' => $this->description,
            'incurred_at' => $this->incurred_at->toISOString(),
            'receipt_path' => $this->receipt_path,
            'created_by' => $this->created_by,
            'account' => $this->whenLoaded('account', function () {
                return new FinancialAccountResource($this->account);
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

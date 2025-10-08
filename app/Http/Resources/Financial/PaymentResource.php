<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'transaction_id' => $this->transaction_id,
            'notes' => $this->notes,
            'invoice' => $this->whenLoaded('invoice', [
                'id' => $this->invoice->id,
                'reference' => $this->invoice->reference,
                'total' => (float) $this->invoice->total,
            ]),
            'processor' => $this->when($this->processor, [
                'id' => $this->processor?->id,
                'name' => $this->processor?->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'billable' => [
                'id' => $this->billable_id,
                'type' => $this->billable_type,
                'name' => $this->billable?->name ?? 'N/A',
            ],
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'remaining_balance' => $this->getRemainingBalance(),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'issued_at' => $this->issued_at?->toISOString(),
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return InvoiceItemResource::collection($this->items);
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return PaymentResource::collection($this->payments);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

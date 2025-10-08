<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
            'fee' => $this->when($this->fee, [
                'id' => $this->fee?->id,
                'name' => $this->fee?->name,
                'code' => $this->fee?->code,
            ]),
        ];
    }
}

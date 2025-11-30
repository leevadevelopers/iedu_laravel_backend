<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobilePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'reference_code' => $this->reference_code,
            'provider' => $this->provider,
            'amount' => (float) $this->amount,
            'phone' => $this->phone,
            'status' => $this->status,
            'transaction_id' => $this->transaction_id,
            'instructions' => $this->instructions,
            'initiated_at' => $this->initiated_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                ];
            }),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice->id,
                    'reference' => $this->invoice->reference,
                    'total' => (float) $this->invoice->total,
                ];
            }),
            'payment' => $this->whenLoaded('payment', function () {
                return [
                    'id' => $this->payment->id,
                    'reference' => $this->payment->reference,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


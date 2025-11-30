<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'amount' => (float) $this->amount,
            'phone' => $this->phone,
            'transaction_date' => $this->transaction_date->toISOString(),
            'description' => $this->description,
            'match_status' => $this->match_status,
            'confidence' => $this->confidence,
            'matched_student' => $this->whenLoaded('matchedStudent', function () {
                return [
                    'id' => $this->matchedStudent->id,
                    'name' => $this->matchedStudent->first_name . ' ' . $this->matchedStudent->last_name,
                ];
            }),
            'match_details' => $this->match_details,
        ];
    }
}


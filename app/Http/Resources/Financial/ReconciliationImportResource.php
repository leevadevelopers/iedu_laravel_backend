<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'import_id' => $this->import_id,
            'provider' => $this->provider,
            'period_start' => $this->period_start->toISOString(),
            'period_end' => $this->period_end->toISOString(),
            'status' => $this->status,
            'total_transactions' => $this->total_transactions,
            'matched' => $this->matched,
            'unmatched' => $this->unmatched,
            'pending' => $this->pending,
            'importer' => $this->whenLoaded('importer', function () {
                return [
                    'id' => $this->importer->id,
                    'name' => $this->importer->name,
                ];
            }),
            'transactions' => $this->whenLoaded('transactions', function () {
                return ReconciliationTransactionResource::collection($this->transactions);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}


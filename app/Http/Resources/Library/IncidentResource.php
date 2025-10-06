<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'status' => $this->status,
            'assessed_fine' => $this->assessed_fine,
            'book_copy' => [
                'id' => $this->bookCopy->id,
                'barcode' => $this->bookCopy->barcode,
                'book_title' => $this->bookCopy->book->title,
            ],
            'loan' => $this->when($this->loan, [
                'id' => $this->loan?->id,
                'borrower_name' => $this->loan?->borrower->name,
            ]),
            'reporter' => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ],
            'resolver' => $this->when($this->resolver, [
                'id' => $this->resolver?->id,
                'name' => $this->resolver?->name,
            ]),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

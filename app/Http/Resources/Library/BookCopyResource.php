<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookCopyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'barcode' => $this->barcode,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'is_available' => $this->isAvailable(),
            'book' => $this->whenLoaded('book', function () {
                return [
                    'id' => $this->book->id,
                    'title' => $this->book->title,
                    'isbn' => $this->book->isbn,
                ];
            }),
            'active_loan' => $this->whenLoaded('activeLoan', function () {
                return $this->activeLoan ? [
                    'id' => $this->activeLoan->id,
                    'borrower_id' => $this->activeLoan->borrower_id,
                    'due_at' => $this->activeLoan->due_at?->toISOString(),
                ] : null;
            }),
            'loans_count' => $this->whenCounted('loans'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

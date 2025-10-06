<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => [
                'id' => $this->bookCopy->book->id,
                'title' => $this->bookCopy->book->title,
                'isbn' => $this->bookCopy->book->isbn,
                'cover_image' => $this->bookCopy->book->cover_image,
            ],
            'book_copy' => [
                'id' => $this->bookCopy->id,
                'barcode' => $this->bookCopy->barcode,
                'location' => $this->bookCopy->location,
            ],
            'borrower' => [
                'id' => $this->borrower->id,
                'name' => $this->borrower->name,
                'email' => $this->borrower->email,
            ],
            'loaned_at' => $this->loaned_at->toISOString(),
            'due_at' => $this->due_at->toISOString(),
            'returned_at' => $this->returned_at?->toISOString(),
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'days_overdue' => $this->getDaysOverdue(),
            'fine_amount' => $this->fine_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}

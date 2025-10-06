<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'isbn' => $this->isbn,
            'language' => $this->language,
            'summary' => $this->summary,
            'visibility' => $this->visibility,
            'subjects' => $this->subjects,
            'published_at' => $this->published_at?->format('Y-m-d'),
            'edition' => $this->edition,
            'pages' => $this->pages,
            'cover_image' => $this->cover_image,
            'collection' => [
                'id' => $this->collection?->id,
                'name' => $this->collection?->name,
            ],
            'publisher' => [
                'id' => $this->publisher?->id,
                'name' => $this->publisher?->name,
            ],
            'authors' => $this->whenLoaded('authors', function () {
                return $this->authors->map(fn($author) => [
                    'id' => $author->id,
                    'name' => $author->name,
                ]);
            }),
            'copies_count' => $this->whenCounted('copies'),
            'available_copies_count' => $this->whenLoaded('availableCopies', function () {
                return $this->availableCopies->count();
            }),
            'files' => $this->whenLoaded('files', function () {
                return BookFileResource::collection($this->files);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

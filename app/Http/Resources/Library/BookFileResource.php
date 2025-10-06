<?php

namespace App\Http\Resources\Library;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'type' => $this->type,
            'file_path' => $this->file_path,
            'external_url' => $this->external_url,
            'size' => $this->size,
            'mime' => $this->mime,
            'access_policy' => $this->access_policy,
            'allowed_roles' => $this->allowed_roles,
            'download_url' => $this->when($request->user() && $this->canAccess($request->user()), $this->getUrl()),
            'book' => $this->whenLoaded('book', function () {
                return new BookResource($this->book);
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

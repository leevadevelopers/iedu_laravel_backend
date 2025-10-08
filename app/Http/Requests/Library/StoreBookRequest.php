<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection_id' => 'nullable|exists:library_collections,id',
            'publisher_id' => 'nullable|exists:publishers,id',
            'title' => 'required|string|max:255',
            'isbn' => 'nullable|string|unique:books,isbn',
            'language' => 'required|string|size:2',
            'summary' => 'nullable|string',
            'visibility' => 'required|in:public,tenant,restricted',
            'restricted_tenants' => 'nullable|array',
            'restricted_tenants.*' => 'exists:tenants,id',
            'subjects' => 'nullable|array',
            'published_at' => 'nullable|date',
            'edition' => 'nullable|string|max:50',
            'pages' => 'nullable|integer|min:1',
            'cover_image' => 'nullable|string',
            'author_ids' => 'nullable|array',
            'author_ids.*' => 'exists:authors,id',
        ];
    }
}

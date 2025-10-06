<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => 'required|exists:books,id',
            'type' => 'required|in:pdf,epub,mobi,audio,video,image,other',
            'file_path' => 'nullable|string',
            'external_url' => 'nullable|url',
            'size' => 'nullable|integer|min:0',
            'mime' => 'nullable|string|max:100',
            'access_policy' => 'required|in:public,tenant_only,specific_roles',
            'allowed_roles' => 'required_if:access_policy,specific_roles|array',
            'allowed_roles.*' => 'string',
        ];
    }
}

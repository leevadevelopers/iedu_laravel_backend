<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookCopyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bookCopyId = $this->route('bookCopy')?->id ?? $this->route('bookCopy');

        return [
            'barcode' => 'sometimes|string|max:255|unique:book_copies,barcode,' . $bookCopyId,
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|in:available,loaned,reserved,lost,maintenance',
            'notes' => 'nullable|string',
        ];
    }
}

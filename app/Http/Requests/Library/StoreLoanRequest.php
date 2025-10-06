<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_copy_id' => 'required|exists:book_copies,id',
            'borrower_id' => 'nullable|exists:users,id',
            'loan_days' => 'nullable|integer|min:1|max:90',
            'notes' => 'nullable|string',
        ];
    }
}

<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            

            'loan_id' => 'nullable|exists:loans,id',
            'book_copy_id' => 'required|exists:book_copies,id',
            'type' => 'required|in:damage,loss,other',
            'description' => 'required|string',
        ];
    }
}

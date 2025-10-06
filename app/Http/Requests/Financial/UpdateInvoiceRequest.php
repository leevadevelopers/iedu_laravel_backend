<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subtotal' => 'sometimes|required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'sometimes|required|numeric|min:0',
            'due_at' => 'sometimes|required|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|in:draft,issued,paid,partially_paid,overdue,cancelled',
        ];
    }
}

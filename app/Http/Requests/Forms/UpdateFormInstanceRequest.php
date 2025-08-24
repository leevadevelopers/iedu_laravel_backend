<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instance = $this->route('instance');
        // return $instance->canBeEditedBy(auth()->user());
        return true;
    }

    public function rules(): array
    {
        return [
            'form_data' => 'nullable|array',
            'current_step' => 'nullable|integer|min:1'
        ];
    }
}

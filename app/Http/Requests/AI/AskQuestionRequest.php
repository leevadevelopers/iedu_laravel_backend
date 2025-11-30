<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class AskQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'nullable|string|max:100',
            'question' => 'required|string|max:2000',
            'context' => 'nullable|array',
            'context.grade_level' => 'nullable|string|max:20',
            'context.topic' => 'nullable|string|max:100',
            'image' => 'nullable|string', // base64 encoded image
        ];
    }

    protected function prepareForValidation(): void
    {
        // Remove tenant_id and school_id if provided (set automatically)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}


<?php

namespace App\Http\Requests\Communication;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:sms,portal',
            'class_id' => 'nullable|exists:classes,id',
            'students' => 'nullable|array',
            'students.*' => 'exists:students,id',
            'thread_id' => 'nullable|exists:messages,id',
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


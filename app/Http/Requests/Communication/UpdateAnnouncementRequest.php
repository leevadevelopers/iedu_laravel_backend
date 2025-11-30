<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:5000',
            'recipients' => 'sometimes|array',
            'recipients.*' => 'string|in:all_parents,all_teachers',
            'recipients.class_ids' => 'nullable|array',
            'recipients.class_ids.*' => 'exists:classes,id',
            'channels' => 'sometimes|array|min:1',
            'channels.*' => 'string|in:sms,portal,whatsapp',
            'scheduled_at' => 'nullable|date|after:now',
            'status' => 'sometimes|in:draft,scheduled,published,cancelled',
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


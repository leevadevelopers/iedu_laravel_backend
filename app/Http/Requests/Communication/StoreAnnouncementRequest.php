<?php

namespace App\Http\Requests\Communication;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'recipients' => 'required|array',
            'recipients.*' => 'string|in:all_parents,all_teachers',
            'recipients.class_ids' => 'nullable|array',
            'recipients.class_ids.*' => 'exists:classes,id',
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:sms,portal,whatsapp',
            'scheduled_at' => 'nullable|date|after:now',
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


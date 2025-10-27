<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $userId = Auth::id();

        return [
            'name' => 'sometimes|string|max:255',
            'identifier' => 'sometimes|string|max:255|unique:users,identifier,' . $userId,
            'type' => 'sometimes|in:email,phone',
            'phone' => 'sometimes|nullable|string|max:20',
            'whatsapp_phone' => 'sometimes|nullable|string|max:20',
            'profile_photo_path' => 'sometimes|nullable|string|max:500',
            'settings' => 'sometimes|nullable|array',
            'emergency_contact_json' => 'sometimes|nullable|array',
            'transport_notification_preferences' => 'sometimes|nullable|array',
            'current_password' => 'required_with:identifier|string',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.unique' => 'This identifier is already in use.',
            'type.in' => 'The type must be either email or phone.',
            'current_password.required_with' => 'Current password is required when changing identifier.',
        ];
    }

    protected function prepareForValidation()
    {
        // Trim strings to avoid empty values being rejected
        $this->merge(array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $this->all()));
    }
}

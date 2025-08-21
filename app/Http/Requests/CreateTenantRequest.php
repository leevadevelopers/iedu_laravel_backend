<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('tenants.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:tenants,name',
            'slug' => 'nullable|string|max:255|unique:tenants,slug|regex:/^[a-z0-9-]+$/',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'settings' => 'nullable|array',
            'settings.timezone' => 'nullable|string|timezone',
            'settings.currency' => 'nullable|string|size:3',
            'settings.language' => 'nullable|string|size:2',
            'settings.features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Organization name is required.',
            'name.unique' => 'An organization with this name already exists.',
            'slug.unique' => 'This slug is already taken.',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, and hyphens.',
            'domain.unique' => 'This domain is already registered to another organization.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('slug') && $this->has('name')) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->get('name'))
            ]);
        }

        $this->merge([
            'is_active' => $this->get('is_active', true),
            'settings' => array_merge([
                'timezone' => config('app.timezone'),
                'currency' => 'USD',
                'language' => 'en',
                'features' => [],
            ], $this->get('settings', []))
        ]);
    }
}
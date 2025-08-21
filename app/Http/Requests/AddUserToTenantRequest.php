<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AddUserToTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasTenantPermission('tenants.manage_users');
    }

    public function rules(): array
    {
        $availableRoles = Role::where('guard_name', 'api')->pluck('name')->toArray();

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $tenant = $this->user()->getCurrentTenant();
                    $targetUser = \App\Models\User::find($value);
                    
                    if ($targetUser && $targetUser->belongsToTenant($tenant->id)) {
                        $fail('User already belongs to this organization.');
                    }
                }
            ],
            'role' => ['required', 'string', Rule::in($availableRoles)],
            'custom_permissions' => 'nullable|array',
            'custom_permissions.granted' => 'nullable|array',
            'custom_permissions.granted.*' => 'string|exists:permissions,name',
            'custom_permissions.denied' => 'nullable|array',
            'custom_permissions.denied.*' => 'string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user to add.',
            'user_id.exists' => 'The selected user does not exist.',
            'role.required' => 'Please select a role for the user.',
            'role.in' => 'The selected role is not valid.',
        ];
    }
}
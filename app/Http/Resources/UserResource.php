<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        // Check if this user (the one being displayed) is super_admin
        $isSuperAdmin = method_exists($this->resource, 'isSuperAdmin') && $this->resource->isSuperAdmin();

        // Get tenant pivot data
        // For super_admin: no tenant pivot needed
        // For regular users: get from their first tenant or current tenant context
        $currentUser = $request->user();
        $pivotData = null;
        $tenantId = null;

        if (!$isSuperAdmin) {
            // For regular users, try to get tenant context
            // First, try from request header (if viewing specific tenant)
            if ($request->hasHeader('X-Tenant-ID')) {
                $headerTenantId = (int) $request->header('X-Tenant-ID');
                if ($this->tenants) {
                    $tenant = $this->tenants->firstWhere('id', $headerTenantId);
                    if ($tenant) {
                        $pivotData = $tenant->pivot;
                        $tenantId = $headerTenantId;
                    }
                }
            }
            
            // If no header tenant, try current user's tenant context
            if (!$pivotData && $currentUser) {
                $currentUserTenantId = $currentUser->getCurrentTenant()?->id;
                if ($currentUserTenantId && $this->tenants) {
                    $tenant = $this->tenants->firstWhere('id', $currentUserTenantId);
                    if ($tenant) {
                        $pivotData = $tenant->pivot;
                        $tenantId = $currentUserTenantId;
                    }
                }
            }
            
            // Fallback to first tenant if available
            if (!$pivotData && $this->tenants && $this->tenants->isNotEmpty()) {
                $firstTenant = $this->tenants->first();
                if ($firstTenant) {
                    $pivotData = $firstTenant->pivot;
                    $tenantId = $firstTenant->id;
                }
            }
        }

        // Get role information
        $roleId = null;
        $roleName = null;

        // For super_admin, get role directly from Spatie Permission (not from pivot)
        if ($isSuperAdmin) {
            try {
                $roles = $this->resource->getRoleNames();
                if ($roles->isNotEmpty()) {
                    $roleName = $roles->first();
                    // Try to get role ID and display_name from roles table
                    $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
                    if ($role) {
                        $roleId = $role->id;
                        $roleName = $role->display_name ?? $role->name ?? $roleName;
                    }
                }
            } catch (\Exception $e) {
                // Fallback if roles not available
            }
        } else {
            // For regular users, get role from pivot
            $roleId = $pivotData->role_id ?? null;
        }

        // If we have role_id but no roleName, try to get it from database
        if ($roleId && !$roleName) {
            try {
                // Try Spatie Permission model first
                $role = \Spatie\Permission\Models\Role::find($roleId);
                if ($role) {
                    $roleName = $role->display_name ?? $role->name ?? null;
                } else {
                    // Fallback to DB facade
                    $role = \Illuminate\Support\Facades\DB::table('roles')
                        ->where('id', $roleId)
                        ->first();
                    if ($role) {
                        $roleName = $role->display_name ?? $role->name ?? null;
                    }
                }
            } catch (\Exception $e) {
                // If roles table doesn't exist or query fails, fall back to mapping
            }

            // If we still don't have a role name, try string mapping
            if (!$roleName) {
                $roleMap = [
                    'admin' => 'Administrator',
                    'teacher' => 'Teacher',
                    'student' => 'Student',
                    'parent' => 'Parent',
                    'staff' => 'Staff',
                    'super_admin' => 'Super Administrador',
                    'school_owner' => 'Dono da Escola',
                    'school_admin' => 'Director/Administrador',
                    'accountant' => 'Contabilista',
                    'secretary' => 'Secretária',
                ];

                // If role_id is a string key, use the map
                if (is_string($roleId) && isset($roleMap[$roleId])) {
                    $roleName = $roleMap[$roleId];
                } elseif (is_string($roleId)) {
                    // If it's a string but not in map, capitalize it
                    $roleName = ucfirst($roleId);
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'email' => $this->type === 'email' ? $this->identifier : null, // Map email from identifier
            'type' => $this->type,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'user_type' => $this->user_type,
            'profile_photo_path' => $this->profile_photo_path,
            'is_active' => $this->is_active,
            'verified_at' => $this->verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Status from tenant pivot or default to active
            'status' => $pivotData->status ?? 'active',

            // Role information - simplified for frontend
            'role' => $roleName,
            'role_id' => $roleId,

            'custom_permissions' => $this->when(isset($this->custom_permissions), $this->custom_permissions),
            'tenant_status' => $pivotData->status ?? null,
            'joined_at' => $pivotData->joined_at ?? null,

            'current_tenant_context' => $this->when($request->user()?->id === $this->id, function () {
                return $this->getTenantContext();
            }),
        ];
    }
}

<?php

namespace App\Models\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

trait TenantPermission
{
    public function getTenantPermissions($tenantId = null): array
    {
        // logger()->debug('TenantPermission::getTenantPermissions called', ['tenantId' => $tenantId]);
        $tenantId = $tenantId ?? $this->getCurrentTenantId();

        if (!$tenantId) {
            return [];
        }

        $tenantUser = $this->getTenantUserPivot($tenantId);

        if (!$tenantUser) {
            return [];
        }

        $permissions = [];

        // Get permissions from role
        if ($tenantUser->role_id) {
            $role = Role::find($tenantUser->role_id);
            if ($role) {
                $rolePermissions = $role->permissions->pluck('name')->toArray();
                $permissions = array_merge($permissions, $rolePermissions);
            }
        }

        // Get custom permissions
        if (!empty($tenantUser->permissions)) {
            $customPermissions = json_decode($tenantUser->permissions, true) ?? [];

            if (isset($customPermissions['granted'])) {
                $permissions = array_merge($permissions, $customPermissions['granted']);
            }

            if (isset($customPermissions['denied'])) {
                $permissions = array_diff($permissions, $customPermissions['denied']);
            }

            if (isset($customPermissions[0]) && is_string($customPermissions[0])) {
                $permissions = array_merge($permissions, $customPermissions);
            }
        }

        return array_unique($permissions);
    }

    public function hasTenantPermission($permissions, $tenantId = null): bool
    {
        // logger()->debug('TenantPermission::hasTenantPermission called', ['permissions' => $permissions, 'tenantId' => $tenantId]);
        $tenantId = $tenantId ?? $this->getCurrentTenantId();

        if (!$tenantId) {
            return false;
        }

        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        if ($this->isTenantOwner($tenantId) || $this->hasTenantRole(['super_admin', 'owner'], $tenantId)) {
            return true;
        }

        $userPermissions = $this->getTenantPermissions($tenantId);

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }

            if ($this->checkWildcardPermission($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    public function getTenantRole($tenantId = null): ?Role
    {
        // logger()->debug('TenantPermission::getTenantRole called', ['tenantId' => $tenantId]);
        $tenantId = $tenantId ?? $this->getCurrentTenantId();

        if (!$tenantId) {
            return null;
        }

        $tenantUser = $this->getTenantUserPivot($tenantId);

        if (!$tenantUser || !$tenantUser->role_id) {
            return null;
        }

        return Role::find($tenantUser->role_id);
    }

    public function getTenantRoleName($tenantId = null): ?string
    {
        // logger()->debug('TenantPermission::getTenantRoleName called', ['tenantId' => $tenantId]);
        $role = $this->getTenantRole($tenantId);
        return $role ? $role->name : null;
    }

    public function hasTenantRole($roles, $tenantId = null): bool
    {
        // logger()->debug('TenantPermission::hasTenantRole called', ['roles' => $roles, 'tenantId' => $tenantId]);
        $tenantId = $tenantId ?? $this->getCurrentTenantId();

        if (!$tenantId) {
            return false;
        }

        $roleName = $this->getTenantRoleName($tenantId);

        if (!$roleName) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        return in_array($roleName, $roles);
    }

    public function isTenantOwner($tenantId = null): bool
    {
        // logger()->debug('TenantPermission::isTenantOwner called', ['tenantId' => $tenantId]);
        return $this->hasTenantRole('owner', $tenantId);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // logger()->debug('TenantPermission::hasPermissionTo called', ['permission' => $permission, 'guardName' => $guardName]);

        // Super admin has all permissions
        if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
            return true;
        }

        if ($this->getCurrentTenantId()) {
            return $this->hasTenantPermission($permission);
        }

        // Fallback to the original HasRoles trait method
        return $this->hasRolePermissionTo($permission, $guardName);
    }

    public function hasRole($roles, string $guard = null): bool
    {
        // Normalize roles to array
        $rolesArray = is_array($roles) ? $roles : [$roles];

        // First, check if user is super_admin using base Spatie method (cross-tenant)
        if (in_array('super_admin', $rolesArray) && $this->hasRoleBase('super_admin', $guard ?? 'api')) {
            return true;
        }

        // Then check tenant-specific roles
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            return $this->hasTenantRole($roles);
        }

        // Fallback to the original HasRoles trait method
        return $this->hasRoleBase($roles, $guard);
    }

    public function getTenantContext($tenantId = null): array
    {
        // logger()->debug('TenantPermission::getTenantContext called', ['tenantId' => $tenantId]);
        $tenantId = $tenantId ?? $this->getCurrentTenantId();

        if (!$tenantId) {
            return [
                'tenant_id' => null,
                'role' => null,
                'permissions' => [],
                'is_owner' => false,
            ];
        }

        return [
            'tenant_id' => $tenantId,
            'role' => $this->getTenantRoleName($tenantId),
            'permissions' => $this->getTenantPermissions($tenantId),
            'is_owner' => $this->isTenantOwner($tenantId),
            'custom_permissions' => $this->getCustomTenantPermissions($tenantId),
        ];
    }

    private function getCurrentTenantId(): ?int
    {
        // logger()->debug('TenantPermission::getCurrentTenantId called');
        return session('tenant_id') ??
               cache()->get('tenant_id_' . $this->id) ??
               null;
    }

    private function getTenantUserPivot($tenantId)
    {
        // logger()->debug('TenantPermission::getTenantUserPivot called', ['tenantId' => $tenantId]);
        return DB::table('tenant_users')
            ->where('user_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    private function checkWildcardPermission(string $permission, array $userPermissions): bool
    {
        // logger()->debug('TenantPermission::checkWildcardPermission called', ['permission' => $permission, 'userPermissions' => $userPermissions]);
        foreach ($userPermissions as $userPermission) {
            if (str_ends_with($userPermission, '.*')) {
                $prefix = str_replace('.*', '', $userPermission);
                if (str_starts_with($permission, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getCustomTenantPermissions($tenantId): array
    {
        // logger()->debug('TenantPermission::getCustomTenantPermissions called', ['tenantId' => $tenantId]);
        $tenantUser = $this->getTenantUserPivot($tenantId);

        if (!$tenantUser || !$tenantUser->permissions) {
            return ['granted' => [], 'denied' => []];
        }

        $permissions = json_decode($tenantUser->permissions, true);

        return [
            'granted' => $permissions['granted'] ?? [],
            'denied' => $permissions['denied'] ?? [],
        ];
    }
}

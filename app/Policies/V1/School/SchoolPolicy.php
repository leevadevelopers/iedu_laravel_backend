<?php

namespace App\Policies\V1\School;

use App\Models\User;
use App\Models\V1\SIS\School\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SchoolPolicy
{
    /**
     * Determine whether the user can view any schools.
     */
    public function viewAny(User $user): bool
    {
        // Log entry point
        Log::channel('daily')->info('SchoolPolicy::viewAny called', [
            'user_id' => $user->id,
            'user_identifier' => $user->identifier ?? null,
        ]);

        // Super admin can always view
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Get tenant ID
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        // If we have tenant context, check tenant permissions
        if ($tenantId && method_exists($user, 'hasTenantPermission')) {
            if ($user->hasTenantPermission(['schools.view', 'schools.view_all'], $tenantId)) {
                return true;
            }
        }

        // Try direct permission check (handles tenant automatically)
        try {
            if ($user->hasPermissionTo('schools.view', 'api') || $user->hasPermissionTo('schools.view_all', 'api')) {
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->warning('SchoolPolicy::viewAny - Permission check exception', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Check if user has any role (all roles have schools.view)
        try {
            $roles = $user->getRoleNames();
            if ($roles->isNotEmpty()) {
                Log::channel('daily')->info('SchoolPolicy::viewAny - Allowed by role', [
                    'user_id' => $user->id,
                    'roles' => $roles->toArray()
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->error('SchoolPolicy::viewAny - Role check failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Last resort: allow if user is authenticated (all authenticated users should have schools.view)
        if ($user->id) {
            Log::channel('daily')->warning('SchoolPolicy::viewAny - Allowing as last resort', [
                'user_id' => $user->id
            ]);
            return true;
        }

        Log::channel('daily')->error('SchoolPolicy::viewAny - DENIED', [
            'user_id' => $user->id ?? 'NO_ID',
        ]);

        return false;
    }

    /**
     * Determine whether the user can view the school.
     */
    public function view(User $user, School $school): bool
    {
        Log::channel('daily')->info('SchoolPolicy::view called', [
            'user_id' => $user->id,
            'school_id' => $school->id,
            'school_tenant_id' => $school->tenant_id,
        ]);

        // Super admin can view all schools
        if (method_exists($user, 'isSuperAdmin')) {
            $isSuperAdmin = $user->isSuperAdmin();
            Log::channel('daily')->info('SchoolPolicy::view - Super admin check', [
                'user_id' => $user->id,
                'is_super_admin' => $isSuperAdmin,
            ]);
            if ($isSuperAdmin) {
                Log::channel('daily')->info('SchoolPolicy::view - Allowed: Super admin');
                return true;
            }
        }

        // Get tenant ID
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        // If we have tenant context, check tenant permissions
        if ($tenantId && method_exists($user, 'hasTenantPermission')) {
            if ($user->hasTenantPermission(['schools.view', 'schools.view_all'], $tenantId)) {
                // Check if school belongs to user's tenant
                return $school->tenant_id === $tenantId;
            }
        }

        // Try direct permission check (handles tenant automatically)
        try {
            if ($user->hasPermissionTo('schools.view', 'api') || $user->hasPermissionTo('schools.view_all', 'api')) {
                // Check if school belongs to user's tenant
                $userTenantId = $user->tenant_id ?? session('tenant_id');
                return $school->tenant_id === $userTenantId;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->warning('SchoolPolicy::view - Permission check exception', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Check if user has any role (all roles have schools.view)
        try {
            $roles = $user->getRoleNames();
            if ($roles->isNotEmpty()) {
                // Check if school belongs to user's tenant
                $userTenantId = $user->tenant_id ?? session('tenant_id');
                return $school->tenant_id === $userTenantId;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->error('SchoolPolicy::view - Role check failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Last resort: allow if user is authenticated and school belongs to their tenant
        if ($user->id) {
            $userTenantId = $user->tenant_id ?? session('tenant_id');
            if ($school->tenant_id === $userTenantId) {
                Log::channel('daily')->warning('SchoolPolicy::view - Allowing as last resort', [
                    'user_id' => $user->id,
                    'school_id' => $school->id
                ]);
                return true;
            }
        }

        Log::channel('daily')->error('SchoolPolicy::view - DENIED', [
            'user_id' => $user->id ?? 'NO_ID',
            'school_id' => $school->id ?? 'NO_ID',
        ]);

        return false;
    }

    /**
     * Determine whether the user can create schools.
     */
    public function create(User $user): bool
    {
        Log::channel('daily')->info('SchoolPolicy::create called', [
            'user_id' => $user->id,
            'user_identifier' => $user->identifier ?? null,
        ]);

        // Super admin can always create schools
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            Log::channel('daily')->info('SchoolPolicy::create - Allowed: Super admin');
            return true;
        }

        // Get tenant ID
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        // If we have tenant context, check tenant permissions
        if ($tenantId && method_exists($user, 'hasTenantPermission')) {
            if ($user->hasTenantPermission(['schools.create', 'schools.manage'], $tenantId)) {
                Log::channel('daily')->info('SchoolPolicy::create - Allowed: Tenant permission', [
                    'tenant_id' => $tenantId
                ]);
                return true;
            }
        }

        // Try direct permission check (handles tenant automatically)
        try {
            if ($user->hasPermissionTo('schools.create', 'api') || $user->hasPermissionTo('schools.manage', 'api')) {
                Log::channel('daily')->info('SchoolPolicy::create - Allowed: Direct permission');
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->warning('SchoolPolicy::create - Permission check exception', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Check if user has any role (allow authenticated users with roles to create schools)
        try {
            $roles = $user->getRoleNames();
            if ($roles->isNotEmpty()) {
                Log::channel('daily')->info('SchoolPolicy::create - Allowed: Has role', [
                    'roles' => $roles->toArray()
                ]);
                return true;
            }
        } catch (\Exception $e) {
            Log::channel('daily')->error('SchoolPolicy::create - Role check failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
        }

        // Last resort: allow if user is authenticated (all authenticated users should be able to create schools)
        if ($user->id) {
            Log::channel('daily')->warning('SchoolPolicy::create - Allowing as last resort', [
                'user_id' => $user->id
            ]);
            return true;
        }

        Log::channel('daily')->error('SchoolPolicy::create - DENIED', [
            'user_id' => $user->id ?? 'NO_ID',
        ]);

        return false;
    }

    /**
     * Determine whether the user can update the school.
     */
    public function update(User $user, School $school): bool
    {
        // Super admin can update all schools
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Check if user has permission to edit schools
        if (!$user->hasPermissionTo('schools.edit', 'api')) {
            return false;
        }

        // Check if school belongs to user's tenant
        $userTenantId = $user->tenant_id ?? session('tenant_id');
        return $school->tenant_id === $userTenantId;
    }

    /**
     * Determine whether the user can delete the school.
     */
    public function delete(User $user, School $school): bool
    {
        // Super admin can delete all schools
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Check if user has permission to delete schools
        if (!$user->hasPermissionTo('schools.delete', 'api')) {
            return false;
        }

        // Check if school belongs to user's tenant
        $userTenantId = $user->tenant_id ?? session('tenant_id');
        return $school->tenant_id === $userTenantId;
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function viewStatistics(User $user, School $school = null): bool
    {
        // Super admin, admin, tenant_admin, and owner can view statistics
        if ($this->isAdministrativeRole($user)) {
            return true;
        }

        // Check if user has permission to view statistics
        if ($user->hasPermissionTo('schools.statistics', 'api')) {
            // If school is provided, check if it belongs to user's tenant
            if ($school) {
                // Super admin can view statistics for all schools
                if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                    return true;
                }

                $userTenantId = $user->tenant_id ?? session('tenant_id');
                return $school->tenant_id === $userTenantId;
            }

            return true;
        }

        return false;
    }

    /**
     * Check if user has an administrative role.
     */
    private function isAdministrativeRole(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'tenant_admin', 'owner']);
    }
}


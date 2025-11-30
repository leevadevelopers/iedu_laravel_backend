<?php

namespace App\Policies\Library;

use App\Models\V1\Library\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('library.books.view');
    }

    public function view(User $user, Book $book): bool
    {
        if (!$user->hasPermissionTo('library.books.view')) {
            return false;
        }

        // Check visibility rules
        if ($book->visibility === 'public') {
            return true;
        }

        if ($book->visibility === 'tenant') {
            return $book->tenant_id === $user->tenant_id;
        }

        if ($book->visibility === 'restricted') {
            return in_array($user->tenant_id, $book->restricted_tenants ?? []);
        }

        return false;
    }

    public function create(User $user): bool
    {
        // Super admin has all permissions
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Get tenant ID
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        // Owners and super_admins of tenant have all permissions
        if ($tenantId && method_exists($user, 'isTenantOwner')) {
            if ($user->isTenantOwner($tenantId) || $user->hasTenantRole(['super_admin', 'owner'], $tenantId)) {
                return true;
            }
        }

        // Use hasTenantPermission which accepts arrays and handles tenant context
        return $user->hasTenantPermission(['library.books.create', 'library.manage']);
    }

    public function update(User $user, Book $book): bool
    {
        // Super admin has all permissions
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Get user tenant_id (from session or user model)
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        // Only the tenant that owns the book can update
        if ($book->tenant_id !== $userTenantId) {
            return false;
        }

        // Owners and super_admins of tenant have all permissions
        if ($userTenantId && method_exists($user, 'isTenantOwner')) {
            if ($user->isTenantOwner($userTenantId) || $user->hasTenantRole(['super_admin', 'owner'], $userTenantId)) {
                return true;
            }
        }

        // Check permissions using hasTenantPermission (accepts arrays)
        return $user->hasTenantPermission(['library.books.update', 'library.manage']);
    }

    public function delete(User $user, Book $book): bool
    {
        // Super admin has all permissions
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Get user tenant_id (from session or user model)
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        // Only the tenant that owns the book can delete
        if ($book->tenant_id !== $userTenantId) {
            return false;
        }

        // Owners and super_admins of tenant have all permissions
        if ($userTenantId && method_exists($user, 'isTenantOwner')) {
            if ($user->isTenantOwner($userTenantId) || $user->hasTenantRole(['super_admin', 'owner'], $userTenantId)) {
                return true;
            }
        }

        // Check permissions using hasTenantPermission (accepts arrays)
        return $user->hasTenantPermission(['library.books.delete', 'library.manage']);
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class PermissionController extends Controller
{
    /**
     * Get all permissions grouped by category
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'api')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        return response()->json([
            'permissions' => $permissions,
            'categories' => $permissions->keys()
        ]);
    }

    /**
     * Get all roles with their permissions
     */
    public function roles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'api')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                    'description' => $role->description,
                    'is_system' => $role->is_system ?? false,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'permission_count' => $role->permissions->count()
                ];
            });

        return response()->json(['roles' => $roles]);
    }

    /**
     * Get a specific role with its details
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system ?? false,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'permission_count' => $role->permissions->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at
            ]
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        // Check if user has permission to create roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'is_system' => false,
                'guard_name' => 'api'
            ]);

            if ($request->permissions) {
                $role->syncPermissions($request->permissions);
            }

            // Log the activity
            activity()
                ->performedOn($role)
                ->withProperties([
                    'permissions' => $request->permissions ?? []
                ])
                ->log('role_created');

            DB::commit();

            return response()->json([
                'message' => 'Role created successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'permission_count' => $role->permissions->count()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create role'], 500);
        }
    }

    /**
     * Update a role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500'
        ]);

        // Check if user has permission to manage roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Prevent modification of system roles
        if ($role->is_system) {
            return response()->json(['error' => 'Cannot modify system roles'], 403);
        }

        try {
            DB::beginTransaction();

            $role->update([
                'display_name' => $request->display_name,
                'description' => $request->description
            ]);

            // Log the activity
            activity()
                ->performedOn($role)
                ->withProperties([
                    'old_display_name' => $role->getOriginal('display_name'),
                    'new_display_name' => $request->display_name,
                    'old_description' => $role->getOriginal('description'),
                    'new_description' => $request->description
                ])
                ->log('role_updated');

            DB::commit();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'permission_count' => $role->permissions->count()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update role'], 500);
        }
    }

    /**
     * Delete a role
     */
    public function destroy(Role $role): JsonResponse
    {
        // Check if user has permission to manage roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Prevent deletion of system roles
        if ($role->is_system) {
            return response()->json(['error' => 'Cannot delete system roles'], 403);
        }

        // Check if role is assigned to any users
        $usersWithRole = DB::table('tenant_users')
            ->where('role_id', $role->id)
            ->count();

        if ($usersWithRole > 0) {
            return response()->json([
                'error' => 'Cannot delete role that is assigned to users',
                'users_count' => $usersWithRole
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Log the activity before deletion
            activity()
                ->performedOn($role)
                ->log('role_deleted');

            $role->delete();

            DB::commit();

            return response()->json([
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete role'], 500);
        }
    }

    /**
     * Get permissions for a specific role
     */
    public function rolePermissions(Role $role): JsonResponse
    {
        $permissions = $role->permissions->pluck('name')->toArray();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system ?? false
            ],
            'permissions' => $permissions
        ]);
    }

    /**
     * Update permissions for a role
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        // Check if user has permission to manage roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        // Prevent modification of system roles
        if ($role->is_system) {
            return response()->json(['error' => 'Cannot modify system roles'], 403);
        }

        try {
            DB::beginTransaction();

            // Sync permissions
            $role->syncPermissions($request->permissions);

            // Log the activity
            activity()
                ->performedOn($role)
                ->withProperties([
                    'old_permissions' => $role->permissions->pluck('name')->toArray(),
                    'new_permissions' => $request->permissions
                ])
                ->log('role_permissions_updated');

            DB::commit();

            return response()->json([
                'message' => 'Role permissions updated successfully',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                    'permissions' => $request->permissions
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update role permissions'], 500);
        }
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        // Check if user has permission to manage user roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $currentUser = Auth::user();
        $currentTenant = $currentUser->getCurrentTenant();

        if (!$currentTenant) {
            return response()->json(['error' => 'No active tenant found'], 404);
        }

        $tenantId = $currentTenant->id;

        // Get the target user's tenant relationship
        $targetUser = User::findOrFail($request->user_id);
        $tenantUser = $targetUser->tenants()->where('tenant_id', $tenantId)->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in current tenant'], 404);
        }

        try {
            DB::beginTransaction();

            // Update tenant user role
            $targetUser->tenants()->updateExistingPivot($tenantId, [
                'role_id' => $request->role_id
            ]);

            // Log the activity
            activity()
                ->performedOn($targetUser)
                ->withProperties([
                    'tenant_id' => $tenantId,
                    'old_role_id' => $tenantUser->role_id,
                    'new_role_id' => $request->role_id
                ])
                ->log('user_role_assigned');

            DB::commit();

            return response()->json([
                'message' => 'Role assigned to user successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to assign role to user'], 500);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        // Check if user has permission to manage user roles
        if (!Auth::user()->hasTenantPermission('users.manage_roles')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $currentUser = Auth::user();
        $currentTenant = $currentUser->getCurrentTenant();

        if (!$currentTenant) {
            return response()->json(['error' => 'No active tenant found'], 404);
        }

        $tenantId = $currentTenant->id;

        // Get the target user's tenant relationship
        $targetUser = User::findOrFail($request->user_id);
        $tenantUser = $targetUser->tenants()->where('tenant_id', $tenantId)->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in current tenant'], 404);
        }

        if (!$tenantUser->role_id) {
            return response()->json(['error' => 'User has no role assigned'], 400);
        }

        try {
            DB::beginTransaction();

            // Remove role from tenant user
            $targetUser->tenants()->updateExistingPivot($tenantId, [
                'role_id' => null
            ]);

            // Log the activity
            activity()
                ->performedOn($targetUser)
                ->withProperties([
                    'tenant_id' => $tenantId,
                    'removed_role_id' => $tenantUser->role_id
                ])
                ->log('user_role_removed');

            DB::commit();

            return response()->json([
                'message' => 'Role removed from user successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to remove role from user'], 500);
        }
    }

    /**
     * Get user permissions for the current tenant
     */
    public function userPermissions(): JsonResponse
    {
        $user = Auth::user();
        $currentTenant = $user->getCurrentTenant();

        if (!$currentTenant) {
            return response()->json(['error' => 'No active tenant found'], 404);
        }

        $tenantId = $currentTenant->id;
        $tenantUser = $user->tenants()->where('tenant_id', $tenantId)->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in current tenant'], 404);
        }

        $permissions = $user->getTenantPermissions($tenantId);
        $role = $user->getTenantRole($tenantId);
        $customPermissions = $user->getCustomTenantPermissions($tenantId);

        return response()->json([
            'permissions' => $permissions,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? $role->name
            ] : null,
            'custom_permissions' => $customPermissions,
            'tenant_user' => [
                'role_id' => $tenantUser->role_id,
                'permissions' => $tenantUser->permissions
            ]
        ]);
    }

    /**
     * Update custom permissions for a user in the current tenant
     */
    public function updateUserPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'granted_permissions' => 'array',
            'granted_permissions.*' => 'string|exists:permissions,name',
            'denied_permissions' => 'array',
            'denied_permissions.*' => 'string|exists:permissions,name'
        ]);

        // Check if user has permission to manage user permissions
        if (!Auth::user()->hasTenantPermission('users.manage_permissions')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $currentUser = Auth::user();
        $currentTenant = $currentUser->getCurrentTenant();

        if (!$currentTenant) {
            return response()->json(['error' => 'No active tenant found'], 404);
        }

        $tenantId = $currentTenant->id;

        // Get the target user's tenant relationship
        $targetUser = User::findOrFail($request->user_id);
        $tenantUser = $targetUser->tenants()->where('tenant_id', $tenantId)->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found in current tenant'], 404);
        }

        try {
            DB::beginTransaction();

            // Prepare custom permissions
            $customPermissions = [
                'granted' => $request->granted_permissions ?? [],
                'denied' => $request->denied_permissions ?? []
            ];

            // Update tenant user permissions using updateExistingPivot
            $result = $targetUser->tenants()->updateExistingPivot($tenantId, [
                'permissions' => json_encode($customPermissions)
            ]);

            // Log the activity
            activity()
                ->performedOn($targetUser)
                ->withProperties([
                    'tenant_id' => $tenantId,
                    'granted_permissions' => $customPermissions['granted'],
                    'denied_permissions' => $customPermissions['denied']
                ])
                ->log('user_permissions_updated');

            DB::commit();

            // Get updated permissions for response
            $updatedPermissions = $targetUser->getTenantPermissions($tenantId);

            return response()->json([
                'message' => 'User permissions updated successfully',
                'user_permissions' => $updatedPermissions
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update user permissions'], 500);
        }
    }

    /**
     * Get permission matrix data for the frontend
     */
    public function matrix(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'api')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $roles = Role::where('guard_name', 'api')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name ?? $role->name,
                    'description' => $role->description,
                    'is_system' => $role->is_system ?? false,
                    'permissions' => $role->permissions->pluck('name')->toArray()
                ];
            });

        return response()->json([
            'permissions' => $permissions,
            'roles' => $roles,
            'categories' => $permissions->keys()
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantService
{
    public function createTenant(array $data, User $creator): Tenant
    {
        return DB::transaction(function () use ($data, $creator) {
            if (!isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }
            
            $data['slug'] = $this->ensureUniqueSlug($data['slug']);
            
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'domain' => $data['domain'] ?? null,
                'settings' => $data['settings'] ?? [],
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $creator->id,
            ]);
            
            $this->addUserToTenant($tenant, $creator, 'owner', true);
            
            return $tenant;
        });
    }

    public function addUserToTenant(
        Tenant $tenant, 
        User $user, 
        string $roleName = 'member',
        bool $setAsCurrent = false,
        array $customPermissions = []
    ): void {
        $role = Role::where('name', $roleName)->first();
        
        if (!$role) {
            throw new \InvalidArgumentException("Role '{$roleName}' does not exist");
        }

        if ($user->belongsToTenant($tenant->id)) {
            throw new \InvalidArgumentException('User already belongs to this tenant');
        }

        if ($setAsCurrent) {
            $user->tenants()->updateExistingPivot(
                $user->tenants()->pluck('tenants.id'), 
                ['current_tenant' => false]
            );
        }

        $user->tenants()->attach($tenant->id, [
            'role_id' => $role->id,
            'permissions' => !empty($customPermissions) ? json_encode($customPermissions) : null,
            'current_tenant' => $setAsCurrent,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        
        if ($setAsCurrent) {
            session(['tenant_id' => $tenant->id]);
        }
    }

    public function removeUserFromTenant(Tenant $tenant, User $user): void
    {
        if (!$user->belongsToTenant($tenant->id)) {
            throw new \InvalidArgumentException('User does not belong to this tenant');
        }

        $userRole = $user->getTenantRoleName($tenant->id);
        if ($userRole === 'owner') {
            $ownerCount = $tenant->users()
                ->wherePivot('role_id', function($query) {
                    $query->select('id')->from('roles')->where('name', 'owner');
                })
                ->wherePivot('status', 'active')
                ->count();
                
            if ($ownerCount <= 1) {
                throw new \InvalidArgumentException('Cannot remove the last owner from tenant');
            }
        }

        $user->tenants()->detach($tenant->id);
        
        if (session('tenant_id') == $tenant->id) {
            session()->forget('tenant_id');
        }
    }

    public function updateUserRole(
        Tenant $tenant, 
        User $user, 
        string $newRoleName,
        array $customPermissions = []
    ): void {
        if (!$user->belongsToTenant($tenant->id)) {
            throw new \InvalidArgumentException('User does not belong to this tenant');
        }

        $newRole = Role::where('name', $newRoleName)->first();
        if (!$newRole) {
            throw new \InvalidArgumentException("Role '{$newRoleName}' does not exist");
        }

        $currentRole = $user->getTenantRoleName($tenant->id);
        if ($currentRole === 'owner' && $newRoleName !== 'owner') {
            $ownerCount = $tenant->users()
                ->wherePivot('role_id', function($query) {
                    $query->select('id')->from('roles')->where('name', 'owner');
                })
                ->wherePivot('status', 'active')
                ->count();
                
            if ($ownerCount <= 1) {
                throw new \InvalidArgumentException('Cannot change role of the last owner');
            }
        }

        $user->tenants()->updateExistingPivot($tenant->id, [
            'role_id' => $newRole->id,
            'permissions' => !empty($customPermissions) ? json_encode($customPermissions) : null,
        ]);
    }

    public function getTenantUsers(Tenant $tenant, string $status = 'active'): Collection
    {
        return $tenant->users()
            ->wherePivot('status', $status)
            ->with(['roles' => function($query) {
                $query->select('id', 'name', 'display_name');
            }])
            ->get()
            ->map(function($user) {
                $user->tenant_role = Role::find($user->pivot->role_id);
                $user->custom_permissions = $user->pivot->permissions ? 
                    json_decode($user->pivot->permissions, true) : [];
                $user->tenant_status = $user->pivot->status;
                $user->joined_at = $user->pivot->joined_at;
                return $user;
            });
    }

    public function switchUserTenant(User $user, int $tenantId): bool
    {
        return $user->switchTenant($tenantId);
    }

    public function updateTenantSettings(Tenant $tenant, array $settings): Tenant
    {
        $currentSettings = $tenant->settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);
        
        $tenant->update(['settings' => $mergedSettings]);
        
        return $tenant->fresh();
    }

    public function deactivateTenant(Tenant $tenant): void
    {
        $tenant->update(['is_active' => false]);
        
        $tenant->users()->each(function($user) use ($tenant) {
            if (session('tenant_id') == $tenant->id) {
                session()->forget('tenant_id');
            }
        });
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $originalSlug = $slug;
        $counter = 1;
        
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    public function getTenantStats(Tenant $tenant): array
    {
        return [
            'total_users' => $tenant->users()->count(),
            'active_users' => $tenant->users()->wherePivot('status', 'active')->count(),
            'inactive_users' => $tenant->users()->wherePivot('status', 'inactive')->count(),
            'suspended_users' => $tenant->users()->wherePivot('status', 'suspended')->count(),
            'owners' => $tenant->users()
                ->wherePivot('role_id', function($query) {
                    $query->select('id')->from('roles')->where('name', 'owner');
                })
                ->wherePivot('status', 'active')
                ->count(),
            'created_at' => $tenant->created_at,
            'is_active' => $tenant->is_active,
        ];
    }
}
<?php

namespace App\Models;

use App\Models\Settings\Tenant;
use App\Models\Traits\TenantPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, TenantPermission {
        TenantPermission::hasPermissionTo insteadof HasRoles;
        HasRoles::hasPermissionTo as hasRolePermissionTo;
        TenantPermission::hasRole insteadof HasRoles;
        HasRoles::hasRole as hasRoleBase;
    }

    protected $guard_name = 'api';

    protected $fillable = [
        'tenant_id',
        'name',
        'identifier',
        'type',
        'verified_at',
        'password',
        'must_change',
        'remember_token',
        'phone',
        'company',
        'job_title',
        'bio',
        'profile_photo_path',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'verified_at' => 'datetime',
        'must_change' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['role_id', 'permissions', 'current_tenant', 'joined_at', 'status'])
            ->withTimestamps();
    }

    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->where('tenants.is_active', true)
            ->wherePivot('status', 'active');
    }

    public function getCurrentTenant(): ?Tenant
    {
        // Check session first
        $tenantId = session('tenant_id');

        if ($tenantId) {
            // Use cache to avoid repeated database queries
            $cacheKey = "tenant_{$tenantId}";
            $cachedTenant = Cache::get($cacheKey);

            if ($cachedTenant !== null) {
                return $cachedTenant;
            }

            // Query database and cache result
            $tenant = $this->tenants()->find($tenantId);
            if ($tenant) {
                Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
                return $tenant;
            }
        }

        // If no session or cached tenant, query database
        $cacheKey = "user_current_tenant_{$this->id}";
        $cachedTenant = Cache::get($cacheKey);

        if ($cachedTenant !== null) {
            session(['tenant_id' => $cachedTenant->id]);
            return $cachedTenant;
        }

        // Query for current tenant
        $tenant = $this->tenants()->wherePivot('current_tenant', true)->first();

        if ($tenant) {
            session(['tenant_id' => $tenant->id]);
            Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
            return $tenant;
        }

        // Fallback to first tenant
        $tenant = $this->tenants()->first();
        if ($tenant) {
            session(['tenant_id' => $tenant->id]);
            Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
            return $tenant;
        }

        return null;
    }

    public function switchTenant(int $tenantId): bool
    {
        if (!$this->tenants()->where('tenants.id', $tenantId)->exists()) {
            return false;
        }

        $this->tenants()->updateExistingPivot($this->tenants()->pluck('tenants.id'), [
            'current_tenant' => false
        ]);

        $this->tenants()->updateExistingPivot($tenantId, ['current_tenant' => true]);
        session(['tenant_id' => $tenantId]);

        // Clear tenant cache
        $this->clearTenantCache();

        return true;
    }

    /**
     * Clear tenant-related cache
     */
    public function clearTenantCache(): void
    {
        $oldTenantId = session('tenant_id');
        if ($oldTenantId) {
            Cache::forget("tenant_{$oldTenantId}");
        }
        Cache::forget("user_current_tenant_{$this->id}");
        Cache::forget("user_tenant_{$this->id}");
    }

    public function belongsToTenant(int $tenantId): bool
    {
        logger()->debug('User::belongsToTenant called', ['tenantId' => $tenantId]);
        return $this->tenants()
            ->where('tenants.id', $tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->whereHas('tenants', function ($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId)->wherePivot('status', 'active');
        });
    }
}

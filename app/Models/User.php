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

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, TenantPermission {
        TenantPermission::hasPermissionTo insteadof HasRoles;
        HasRoles::hasPermissionTo as hasRolePermissionTo;
        TenantPermission::hasRole insteadof HasRoles;
        HasRoles::hasRole as hasRoleBase;
    }

    protected $fillable = [
        'name', 'identifier', 'type', 'verified_at', 'password', 'must_change', 'remember_token', 'created_at', 'updated_at',
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
            'email' => $this->email,
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
        logger()->debug('User::getCurrentTenant called');
        $tenantId = session('tenant_id');
        
        if (!$tenantId) {
            $tenant = $this->tenants()->wherePivot('current_tenant', true)->first();
                
            if ($tenant) {
                session(['tenant_id' => $tenant->id]);
                return $tenant;
            }
            
            $tenant = $this->tenants()->first();
            if ($tenant) {
                session(['tenant_id' => $tenant->id]);
                return $tenant;
            }
        }
        
        return $this->tenants()->find($tenantId);
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
        
        return true;
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
        return $query->whereHas('tenants', function($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId)->wherePivot('status', 'active');
        });
    }
}

<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Tenantable
{
    protected static function bootTenantable()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                // Set tenant_id from session (set by TenantMiddleware)
                $model->tenant_id = session('tenant_id') ?? null;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        if ($tenantId === null) {
            // Use session first, then header as fallback
                $tenantId = session('tenant_id');
                if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) request()->header('X-Tenant-ID');
            }
        }

        if ($tenantId) {
            return $query->where($this->getTable() . '.tenant_id', $tenantId);
        }

        return $query;
    }

    public function scopeForTenants($query, array $tenantIds)
    {
        return $query->whereIn($this->getTable() . '.tenant_id', $tenantIds);
    }

    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id == $tenantId;
    }

    public function belongsToCurrentTenant(): bool
    {
            $currentTenantId = session('tenant_id');
            if (!$currentTenantId && request()->hasHeader('X-Tenant-ID')) {
                $currentTenantId = (int) request()->header('X-Tenant-ID');
        }
        return $currentTenantId && $this->belongsToTenant($currentTenantId);
    }

    public static function createForTenant(array $attributes = [], $tenantId = null): static
    {
        if ($tenantId === null) {
                $tenantId = session('tenant_id');
                if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) request()->header('X-Tenant-ID');
            }
        }

        if (!$tenantId) {
            throw new \Exception('Cannot create model without tenant context');
        }

        $attributes['tenant_id'] = $tenantId;

        return static::create($attributes);
    }

    public function userCanAccess($user = null): bool
    {
        $user = $user ?? auth('api')->user();

        if (!$user) {
            return false;
        }

        return $user->belongsToTenant($this->tenant_id);
    }

    protected function allowTenantChange(): bool
    {
        return false;
    }

    public static function withoutTenantScope(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}

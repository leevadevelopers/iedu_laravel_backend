<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->getCurrentTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } else {
            if (config('app.env') === 'production') {
                $builder->whereRaw('1 = 0');
            }
        }
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function () use ($builder) {
            return $builder->withoutGlobalScope(TenantScope::class);
        });

        $builder->macro('forTenant', function ($tenantId) use ($builder) {
            return $builder->withoutGlobalScope(TenantScope::class)
                           ->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        });

        $builder->macro('forTenants', function (array $tenantIds) use ($builder) {
            return $builder->withoutGlobalScope(TenantScope::class)
                           ->whereIn($builder->getModel()->getTable() . '.tenant_id', $tenantIds);
        });

        $builder->macro('crossTenant', function () use ($builder) {
            return $builder->withoutGlobalScope(TenantScope::class);
        });
    }

    protected function getCurrentTenantId(): ?int
    {
        // First, try to get from session
        $tenantId = session('tenant_id');

        // Validate and clean session data
        if (!is_null($tenantId) && !is_numeric($tenantId)) {
            session()->forget('tenant_id');
            $tenantId = null;
        }

        // If tenantId is an object with id property
        if (is_object($tenantId) && property_exists($tenantId, 'id')) {
            $tenantId = $tenantId->id;
        }

        // If tenantId is an array with id key
        if (is_array($tenantId) && isset($tenantId['id'])) {
            $tenantId = $tenantId['id'];
        }

        // If we have a valid numeric tenant ID from session, return it
        if ($tenantId !== null && is_numeric($tenantId)) {
            return (int) $tenantId;
        }

        // Try to get from header
        if (request()->hasHeader('X-Tenant-ID')) {
            $headerTenantId = (int) request()->header('X-Tenant-ID');
            $user = auth('api')->user();

            if ($user && $user->belongsToTenant($headerTenantId)) {
                session(['tenant_id' => $headerTenantId]);
                return $headerTenantId;
            }
        }

        // Only query the database if we don't have session data
        $user = auth('api')->user();
        if ($user) {
            // Use cache to avoid repeated database queries
            $cacheKey = "user_tenant_{$user->id}";
            $cachedTenantId = Cache::get($cacheKey);

            if ($cachedTenantId !== null) {
                session(['tenant_id' => $cachedTenantId]);
                return (int) $cachedTenantId;
            }

            // Query database only if not cached
            $tenant = $user->tenants()->wherePivot('current_tenant', true)->first();

            if ($tenant && isset($tenant->id)) {
                session(['tenant_id' => $tenant->id]);
                Cache::put($cacheKey, $tenant->id, 300); // Cache for 5 minutes
                return (int) $tenant->id;
            }

            // Fallback to first tenant
            $tenant = $user->tenants()->first();
            if ($tenant && isset($tenant->id)) {
                session(['tenant_id' => $tenant->id]);
                Cache::put($cacheKey, $tenant->id, 300); // Cache for 5 minutes
                return (int) $tenant->id;
            }
        }

        // Development fallback
        if (config('app.env') !== 'production') {
            return 1; // Default to tenant_id = 1 for seeded data
        }

        return null;
    }
}

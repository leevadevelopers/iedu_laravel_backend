<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = $this->getCurrentTenantId();
        
        Log::debug('TenantScope apply', [
            'tenantId' => $tenantId,
            'type' => gettype($tenantId),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ]);

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } else {
            Log::debug('TenantScope apply - no tenantId', [
                'model' => get_class($model),
                'query' => $builder->toSql(),
                'user_id' => auth('api')->id(),
                'route' => request()->route()?->getName(),
                'url' => request()->fullUrl(),
            ]);
            
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
        $tenantId = session('tenant_id');
        if (!is_null($tenantId) && !is_numeric($tenantId)) {
            Log::warning('TenantScope: Clearing invalid tenant_id from session', ['tenant_id' => $tenantId, 'type' => gettype($tenantId)]);
            session()->forget('tenant_id');
            $tenantId = null;
        }
        Log::debug('TenantScope getCurrentTenantId - session', [
            'tenantId' => $tenantId,
            'type' => gettype($tenantId)
        ]);
        // If tenantId is an object with id property
        if (is_object($tenantId) && property_exists($tenantId, 'id')) {
            $tenantId = $tenantId->id;
            Log::debug('TenantScope getCurrentTenantId - object id', ['tenantId' => $tenantId]);
        }
        // If tenantId is an array with id key
        if (is_array($tenantId) && isset($tenantId['id'])) {
            $tenantId = $tenantId['id'];
            Log::debug('TenantScope getCurrentTenantId - array id', ['tenantId' => $tenantId]);
        }
        // If tenantId is not null, cast to int
        if ($tenantId !== null) {
            if (!is_numeric($tenantId)) {
                Log::warning('TenantScope: tenant_id is not numeric', ['tenant_id' => $tenantId]);
                return null;
            }
            Log::debug('TenantScope getCurrentTenantId - returning int', ['tenantId' => (int)$tenantId]);
            return (int) $tenantId;
        }
        $user = auth('api')->user();
        Log::debug('TenantScope getCurrentTenantId - user', ['user' => $user]);
        if ($user) {
            $tenant = $user->getCurrentTenant();
            Log::debug('TenantScope getCurrentTenantId - user tenant', ['tenant' => $tenant]);
            if ($tenant && isset($tenant->id)) {
                session(['tenant_id' => $tenant->id]);
                Log::debug('TenantScope getCurrentTenantId - user tenant id', ['tenantId' => $tenant->id]);
                return (int) $tenant->id;
            }
        }
        if (request()->hasHeader('X-Tenant-ID')) {
            $headerTenantId = (int) request()->header('X-Tenant-ID');
            Log::debug('TenantScope getCurrentTenantId - header', ['headerTenantId' => $headerTenantId]);
            if ($user && $user->belongsToTenant($headerTenantId)) {
                session(['tenant_id' => $headerTenantId]);
                return $headerTenantId;
            }
        }
        Log::debug('TenantScope getCurrentTenantId - returning null');
        return null;
    }
}
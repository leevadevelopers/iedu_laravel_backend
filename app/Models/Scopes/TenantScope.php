<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Check if user is super_admin - skip tenant filter
        $user = auth('api')->user();
        if ($user && method_exists($user, 'hasRoleBase') && $user->hasRoleBase('super_admin', 'api')) {
                Log::info('TenantScope: Super admin detected, skipping tenant filter', [
                    'user_id' => $user->id,
                    'model' => get_class($model)
                ]);
                return; // Don't apply tenant scope for super_admin
            }

        // Get tenant ID from session (set by TenantMiddleware)
        $tenantId = session('tenant_id');

        // Fallback to header if session not set
        if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
            $tenantId = (int) request()->header('X-Tenant-ID');
        }

        if ($tenantId) {
            $tableName = $model->getTable();
            $builder->where($tableName . '.tenant_id', $tenantId);
        } else {
            // In production, return no results if no tenant context
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
}

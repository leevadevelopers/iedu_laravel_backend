<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

abstract class BaseModel extends Model
{
    /**
     * The "booting" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Automatically scope queries to the current tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $user = Auth::user();
                $tenantId = session('tenant_id') ?? $user->tenant_id ?? $user->current_tenant_id;

                if ($tenantId) {
                    // Only apply tenant scope if the model has tenant_id in fillable
                    $model = $builder->getModel();
                    if (in_array('tenant_id', $model->getFillable()) || property_exists($model, 'tenant_id')) {
                        $builder->where('tenant_id', $tenantId);
                    }
                }
            }
        });
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeTenantScope(Builder $query, ?int $tenantId = null): Builder
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $currentTenantId = session('tenant_id') ?? $user->tenant_id ?? $user->current_tenant_id;

            if ($currentTenantId) {
                return $query->where('tenant_id', $currentTenantId);
            }
        }

        return $query;
    }
}

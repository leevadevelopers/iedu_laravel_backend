<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

trait Tenantable
{
    protected static function bootTenantable()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $tenantId = session('tenant_id') ?? 
                           auth('api')->user()?->getCurrentTenant()?->id ?? 
                           null;
                
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                } else {
                    Log::warning('Creating model without tenant context', [
                        'model' => get_class($model),
                        'user_id' => auth('api')->id(),
                        'session_tenant_id' => session('tenant_id'),
                    ]);
                }
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id')) {
                Log::info('Tenant ID changed for model', [
                    'model' => get_class($model),
                    'model_id' => $model->id,
                    'old_tenant_id' => $model->getOriginal('tenant_id'),
                    'new_tenant_id' => $model->tenant_id,
                    'user_id' => auth('api')->id(),
                ]);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        $tenantId = $tenantId ?? session('tenant_id') ?? auth('api')->user()?->getCurrentTenant()?->id;
        
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
        $currentTenantId = session('tenant_id') ?? auth('api')->user()?->getCurrentTenant()?->id;
        return $currentTenantId && $this->belongsToTenant($currentTenantId);
    }

    public static function createForTenant(array $attributes = [], $tenantId = null): static
    {
        $tenantId = $tenantId ?? session('tenant_id') ?? auth('api')->user()?->getCurrentTenant()?->id;
        
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
<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait Tenantable
{
    protected static function bootTenantable()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $tenantId = null;

                // First: Try to get tenant_id from authenticated user's tenant_id field
                $user = auth('api')->user();
                if ($user && $user instanceof \App\Models\User && $user->tenant_id) {
                    $tenantId = $user->tenant_id;
                    session(['tenant_id' => $tenantId]);
                    Log::info('Tenantable trait set tenant_id from user field', ['tenant_id' => $tenantId, 'model' => get_class($model)]);
                }

                // Second: Try session
                if (!$tenantId) {
                    $tenantId = session('tenant_id');
                    if ($tenantId) {
                        Log::info('Tenantable trait set tenant_id from session', ['tenant_id' => $tenantId, 'model' => get_class($model)]);
                    }
                }

                // Third: Try user's tenant relationship
                if (!$tenantId && $user && $user instanceof \App\Models\User) {
                    // Get tenant directly from user relationship without triggering TenantScope
                    $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                        ->wherePivot('current_tenant', true)->first();
                    if ($tenant && isset($tenant->id)) {
                        $tenantId = $tenant->id;
                        session(['tenant_id' => $tenant->id]);
                        Log::info('Tenantable trait set tenant_id from user relationship (current)', ['tenant_id' => $tenantId, 'model' => get_class($model)]);
                    } else {
                        // Fallback to first tenant
                        $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first();
                        if ($tenant && isset($tenant->id)) {
                            $tenantId = $tenant->id;
                            session(['tenant_id' => $tenant->id]);
                            Log::info('Tenantable trait set tenant_id from user relationship (first)', ['tenant_id' => $tenantId, 'model' => get_class($model)]);
                        }
                    }
                }

                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                } else {
                    Log::warning('Creating model without tenant context', [
                        'model' => get_class($model),
                        'user_id' => auth('api')->id(),
                        'user_tenant_id' => $user->tenant_id ?? 'NULL',
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
        if ($tenantId === null) {
            // Check if we're in a database transaction to avoid infinite loops
            if (DB::transactionLevel() > 0) {
                // During transactions, rely on session or header only
                $tenantId = session('tenant_id');
                if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) request()->header('X-Tenant-ID');
                }
            } else {
                // Use session first, then header, avoid user queries to prevent circular dependency
                $tenantId = session('tenant_id');
                if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) request()->header('X-Tenant-ID');
                }
                // If still no tenant ID, use default for development
                if (!$tenantId && config('app.env') !== 'production') {
                    $tenantId = 1; // Default to tenant_id = 1 for seeded data
                }
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
        // Check if we're in a database transaction to avoid infinite loops
        if (DB::transactionLevel() > 0) {
            // During transactions, rely on session or header only
            $currentTenantId = session('tenant_id');
            if (!$currentTenantId && request()->hasHeader('X-Tenant-ID')) {
                $currentTenantId = (int) request()->header('X-Tenant-ID');
            }
        } else {
            // Avoid circular dependency by getting tenant ID directly
            $currentTenantId = session('tenant_id');
            if (!$currentTenantId) {
                $user = auth('api')->user();
                if ($user && $user instanceof \App\Models\User) {
                    $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                        ->wherePivot('current_tenant', true)->first();
                    if ($tenant) {
                        $currentTenantId = $tenant->id;
                        session(['tenant_id' => $tenant->id]);
                    } else {
                        $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first();
                        if ($tenant) {
                            $currentTenantId = $tenant->id;
                            session(['tenant_id' => $tenant->id]);
                        }
                    }
                }
            }
        }
        return $currentTenantId && $this->belongsToTenant($currentTenantId);
    }

    public static function createForTenant(array $attributes = [], $tenantId = null): static
    {
        if ($tenantId === null) {
            // Check if we're in a database transaction to avoid infinite loops
            if (DB::transactionLevel() > 0) {
                // During transactions, rely on session or header only
                $tenantId = session('tenant_id');
                if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) request()->header('X-Tenant-ID');
                }
            } else {
                // Avoid circular dependency by getting tenant ID directly
                $tenantId = session('tenant_id');
                if (!$tenantId) {
                    $user = auth('api')->user();
                    if ($user && $user instanceof \App\Models\User) {
                        $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                            ->wherePivot('current_tenant', true)->first();
                        if ($tenant) {
                            $tenantId = $tenant->id;
                            session(['tenant_id' => $tenant->id]);
                        } else {
                            $tenant = $user->tenants()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first();
                            if ($tenant) {
                                $tenantId = $tenant->id;
                                session(['tenant_id' => $tenant->id]);
                            }
                        }
                    }
                }
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

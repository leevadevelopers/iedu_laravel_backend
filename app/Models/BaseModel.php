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

        // Automatically scope queries to the current organization
        static::addGlobalScope('organization', function (Builder $builder) {
            if (Auth::check()) {
                $user = Auth::user();
                $tenantId = session('tenant_id') ?? $user->tenant_id;

                if ($tenantId) {
                    // Use withoutGlobalScope to avoid circular dependency when looking up Organization
                    $organization = \App\Models\Organization::withoutGlobalScope('organization')
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if ($organization) {
                        $builder->where('organization_id', $organization->id);
                    }
                }
            }
        });
    }

    /**
     * Scope a query to a specific organization.
     */
    public function scopeOrganizationScope(Builder $query, ?int $organizationId = null): Builder
    {
        if ($organizationId) {
            return $query->where('organization_id', $organizationId);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $tenantId = session('tenant_id') ?? $user->tenant_id;

            if ($tenantId) {
                // Use withoutGlobalScope to avoid circular dependency when looking up Organization
                $organization = \App\Models\Organization::withoutGlobalScope('organization')
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($organization) {
                    return $query->where('organization_id', $organization->id);
                }
            }
        }

        return $query;
    }

    /**
     * Get the organization that owns the model.
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if the model belongs to the current user's organization.
     */
    public function belongsToCurrentOrganization(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        if ($tenantId) {
            // Use withoutGlobalScope to avoid circular dependency when looking up Organization
            $organization = \App\Models\Organization::withoutGlobalScope('organization')
                ->where('tenant_id', $tenantId)
                ->first();
            if ($organization) {
                return $this->organization_id === $organization->id;
            }
        }

        return false;
    }

    /**
     * Boot the model and apply organization scope.
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::withoutGlobalScope('organization');
    }
}

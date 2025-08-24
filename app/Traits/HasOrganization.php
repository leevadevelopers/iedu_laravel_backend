<?php

namespace App\Traits;

use App\Models\Organization;
use App\Traits\HasTenantOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasOrganization
{
    use HasTenantOrganization;

    /**
     * Boot the trait.
     */
    public static function bootHasOrganization(): void
    {
        static::creating(function ($model) {
            if (empty($model->organization_id) && Auth::check()) {
                try {
                    $model->organization_id = (new static())->getCurrentOrganizationId();
                } catch (\Exception $e) {
                    // Organization not found, leave organization_id as null
                }
            }
        });
    }

    /**
     * Get the organization that owns the model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

        /**
     * Scope a query to the current organization.
     */
    public function scopeForCurrentOrganization(Builder $query): Builder
    {
        try {
            return $query->where('organization_id', $this->getCurrentOrganizationId());
        } catch (\Exception $e) {
            return $query;
        }
    }

    /**
     * Scope a query to a specific organization.
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Check if the model belongs to the given organization.
     */
    public function belongsToOrganization(int $organizationId): bool
    {
        return $this->organization_id === $organizationId;
    }

        /**
     * Check if the model belongs to the current user's organization.
     */
    public function belongsToCurrentOrganization(): bool
    {
        try {
            return $this->belongsToOrganization($this->getCurrentOrganizationId());
        } catch (\Exception $e) {
            return false;
        }
    }
}

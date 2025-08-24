<?php

namespace App\Traits;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

trait HasTenantOrganization
{
    /**
     * Get the current user's organization ID from tenant context.
     *
     * @return int|null
     * @throws \Exception
     */
    protected function getCurrentOrganizationId(): ?int
    {
        $user = Auth::user();
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        if (!$tenantId) {
            throw new \Exception('No active tenant found for user');
        }

        $organization = Organization::where('tenant_id', $tenantId)->first();

        if (!$organization) {
            throw new \Exception('No organization found for current tenant');
        }

        return $organization->id;
    }

    /**
     * Get the current user's organization from tenant context.
     *
     * @return Organization|null
     * @throws \Exception
     */
    protected function getCurrentOrganization(): ?Organization
    {
        $user = Auth::user();
        $tenantId = session('tenant_id') ?? $user->tenant_id;

        if (!$tenantId) {
            throw new \Exception('No active tenant found for user');
        }

        return Organization::where('tenant_id', $tenantId)->first();
    }
}

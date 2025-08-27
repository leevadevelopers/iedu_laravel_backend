<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

/**
 * School Context Service
 *
 * Manages school context for multi-tenant school information systems.
 * Provides methods to get current school and tenant context for repository operations.
 */
class SchoolContextService
{
    /**
     * Cache key for school context
     */
    protected const SCHOOL_CONTEXT_CACHE_KEY = 'school_context';

    /**
     * Get the current school ID from context
     */
    public function getCurrentSchoolId(): int
    {
        // Try to get from authenticated user first
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user has a school_id attribute
            if (isset($user->school_id) && $user->school_id) {
                return $user->school_id;
            }

            // Check if user has a school relationship
            if (method_exists($user, 'school') && $user->school) {
                return $user->school->id;
            }
        }

        // Try to get from session
        $schoolId = Session::get('current_school_id');
        if ($schoolId) {
            return (int) $schoolId;
        }

        // Try to get from cache
        $cachedContext = Cache::get(self::SCHOOL_CONTEXT_CACHE_KEY);
        if ($cachedContext && isset($cachedContext['school_id'])) {
            return (int) $cachedContext['school_id'];
        }

        // Fallback to default or throw exception
        throw new \RuntimeException('No school context available. User must be authenticated and have school context set.');
    }

    /**
     * Get the current tenant ID from context
     */
    public function getCurrentTenantId(): int
    {
        // Try to get from authenticated user first
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user has a tenant_id attribute
            if (isset($user->tenant_id) && $user->tenant_id) {
                return $user->tenant_id;
            }

            // Check if user has a tenant relationship
            if (method_exists($user, 'tenant') && $user->tenant) {
                return $user->tenant->id;
            }

            // Try to get tenant through school
            if (method_exists($user, 'school') && $user->school && $user->school->tenant_id) {
                return $user->school->tenant_id;
            }
        }

        // Try to get from session
        $tenantId = Session::get('current_tenant_id');
        if ($tenantId) {
            return (int) $tenantId;
        }

        // Try to get from cache
        $cachedContext = Cache::get(self::SCHOOL_CONTEXT_CACHE_KEY);
        if ($cachedContext && isset($cachedContext['tenant_id'])) {
            return (int) $cachedContext['tenant_id'];
        }

        // Fallback to default or throw exception
        throw new \RuntimeException('No tenant context available. User must be authenticated and have tenant context set.');
    }

    /**
     * Set the current school context
     */
    public function setCurrentSchool(int $schoolId, ?int $tenantId = null): void
    {
        // Store in session
        Session::put('current_school_id', $schoolId);

        if ($tenantId) {
            Session::put('current_tenant_id', $tenantId);
        }

        // Store in cache
        $context = [
            'school_id' => $schoolId,
            'tenant_id' => $tenantId,
            'set_at' => now()->toISOString(),
        ];

        Cache::put(self::SCHOOL_CONTEXT_CACHE_KEY, $context, now()->addHours(24));
    }

    /**
     * Set the current tenant context
     */
    public function setCurrentTenant(int $tenantId): void
    {
        // Store in session
        Session::put('current_tenant_id', $tenantId);

        // Update cache if exists
        $cachedContext = Cache::get(self::SCHOOL_CONTEXT_CACHE_KEY);
        if ($cachedContext) {
            $cachedContext['tenant_id'] = $tenantId;
            $cachedContext['set_at'] = now()->toISOString();
            Cache::put(self::SCHOOL_CONTEXT_CACHE_KEY, $cachedContext, now()->addHours(24));
        }
    }

    /**
     * Clear the current school context
     */
    public function clearCurrentSchool(): void
    {
        Session::forget('current_school_id');
        Session::forget('current_tenant_id');
        Cache::forget(self::SCHOOL_CONTEXT_CACHE_KEY);
    }

    /**
     * Check if school context is available
     */
    public function hasSchoolContext(): bool
    {
        try {
            $this->getCurrentSchoolId();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Check if tenant context is available
     */
    public function hasTenantContext(): bool
    {
        try {
            $this->getCurrentTenantId();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get the current school context as an array
     */
    public function getCurrentContext(): array
    {
        try {
            return [
                'school_id' => $this->getCurrentSchoolId(),
                'tenant_id' => $this->getCurrentTenantId(),
            ];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Validate that the current user has access to the specified school
     */
    public function validateSchoolAccess(int $schoolId): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Super admin can access all schools
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        // Check if user belongs to this school
        if (isset($user->school_id) && $user->school_id === $schoolId) {
            return true;
        }

        // Check if user has school relationship
        if (method_exists($user, 'school') && $user->school && $user->school->id === $schoolId) {
            return true;
        }

        // Check if user has permission to access this school
        // Note: Permission checking is handled by the application's middleware and policies
        // This method focuses on basic school access validation

        return false;
    }

    /**
     * Get all schools accessible to the current user
     */
    public function getAccessibleSchools(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();

        // Super admin can access all schools
        // Note: Role checking is handled by the application's middleware and policies
        // This method focuses on basic school access validation

        // Return user's school if they belong to one
        if (isset($user->school_id)) {
            return [$user->school_id];
        }

        if (method_exists($user, 'school') && $user->school) {
            return [$user->school->id];
        }

        return [];
    }
}

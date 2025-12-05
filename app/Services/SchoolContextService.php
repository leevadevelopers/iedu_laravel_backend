<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

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
            /** @var User $user */
            $user = Auth::user();

            // Use the new getCurrentSchool method that uses school_users relationship
            if (method_exists($user, 'getCurrentSchool')) {
                $currentSchool = $user->getCurrentSchool();
                if ($currentSchool) {
                    return $currentSchool->id;
                }
            }

            // Fallback: Check if user has a school_id attribute (for backward compatibility)
            if (isset($user->school_id) && $user->school_id) {
                return $user->school_id;
            }

            // Check if user has any schools associated via school_users pivot
            if (method_exists($user, 'schools')) {
                $userSchools = $user->schools()
                    ->wherePivot('status', 'active')
                    ->get();
                
                if ($userSchools->count() > 0) {
                    $firstSchool = $userSchools->first();
                    // Set this as the current school in session
                    $this->setCurrentSchool($firstSchool->id, $firstSchool->tenant_id);
                    return $firstSchool->id;
                }
            }

            // Fallback: If user belongs to a tenant, try to get schools from that tenant
            // This is useful when schools act as tenants and users should have access to tenant's schools
            if (method_exists($user, 'getCurrentTenant')) {
                $currentTenant = $user->getCurrentTenant();
                if ($currentTenant) {
                    $tenantSchools = \App\Models\V1\SIS\School\School::where('tenant_id', $currentTenant->id)
                        ->where('status', 'active')
                        ->get();
                    
                    if ($tenantSchools->count() > 0) {
                        $firstSchool = $tenantSchools->first();
                        
                        // Auto-associate user with school if they don't have association yet
                        // This implements the "schools as tenants" pattern
                        if (method_exists($user, 'schools') && !$user->schools()->where('schools.id', $firstSchool->id)->exists()) {
                            // Determine role based on user's tenant role or default to 'owner' for school_owner role
                            $userRole = 'staff';
                            if (method_exists($user, 'hasRole')) {
                                if ($user->hasRole('school_owner')) {
                                    $userRole = 'owner';
                                } elseif ($user->hasRole('school_admin')) {
                                    $userRole = 'admin';
                                } elseif ($user->hasRole('teacher')) {
                                    $userRole = 'teacher';
                                }
                            }
                            
                            $user->schools()->attach($firstSchool->id, [
                                'role' => $userRole,
                                'status' => 'active',
                                'start_date' => now(),
                                'end_date' => null,
                                'permissions' => null,
                            ]);
                        }
                        
                        // Set this as the current school in session
                        $this->setCurrentSchool($firstSchool->id, $firstSchool->tenant_id);
                        return $firstSchool->id;
                    }
                }
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

        // Provide more helpful error message
        if (Auth::check()) {
            $user = Auth::user();
            $schoolCount = 0;
            
            if (method_exists($user, 'schools')) {
                $schoolCount = $user->schools()->wherePivot('status', 'active')->count();
            }

            if ($schoolCount === 0) {
                // Check if tenant has schools but user is not associated
                $tenantHasSchools = false;
                if (method_exists($user, 'getCurrentTenant')) {
                    $currentTenant = $user->getCurrentTenant();
                    if ($currentTenant) {
                        $tenantHasSchools = \App\Models\V1\SIS\School\School::where('tenant_id', $currentTenant->id)
                            ->where('status', 'active')
                            ->exists();
                    }
                }
                
                if ($tenantHasSchools) {
                    throw new \RuntimeException(
                        'No school context available. Your tenant has schools but you are not associated with any. ' .
                        'The system will attempt to auto-associate you on next request, or please contact an administrator.'
                    );
                } else {
                    throw new \RuntimeException(
                        'No school context available. User is not associated with any schools and tenant has no schools. ' .
                        'Please contact an administrator to associate your account with a school.'
                    );
                }
            } else {
                throw new \RuntimeException(
                    'No school context available. User has access to ' . $schoolCount . ' school(s) ' .
                    'but no current school is set. Please select a school or contact an administrator.'
                );
            }
        }

        throw new \RuntimeException('No school context available. User must be authenticated and have school context set.');
    }

    /**
     * Get the current tenant ID from context
     */
    public function getCurrentTenantId(): int
    {
        // Try to get from authenticated user first
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            // Check if user has a tenant_id attribute
            if (isset($user->tenant_id) && $user->tenant_id) {
                return $user->tenant_id;
            }

            // Check if user has a tenant relationship
            if (method_exists($user, 'tenant') && $user->tenant) {
                return $user->tenant->id;
            }

            // Try to get tenant through current school using school_users relationship
            if (method_exists($user, 'getCurrentSchool')) {
                $currentSchool = $user->getCurrentSchool();
                if ($currentSchool && isset($currentSchool->tenant_id) && $currentSchool->tenant_id) {
                    return $currentSchool->tenant_id;
                }
            }

            // Fallback: Try to get tenant through school relationship (backward compatibility)
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

        /** @var User $user */
        $user = Auth::user();

        // Super admin can access all schools
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        // Check if user belongs to this school through school_users relationship
        if (method_exists($user, 'activeSchools')) {
            return $user->activeSchools()->where('schools.id', $schoolId)->exists();
        }

        // Fallback: Check if user belongs to this school via school_id attribute
        if (isset($user->school_id) && $user->school_id === $schoolId) {
            return true;
        }

        // Fallback: Check if user has school relationship
        if (method_exists($user, 'school') && $user->school && $user->school->id === $schoolId) {
            return true;
        }

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

        /** @var User $user */
        $user = Auth::user();

        // Super admin can access all schools
        // Note: Role checking is handled by the application's middleware and policies
        // This method focuses on basic school access validation

        // Get schools through school_users relationship
        if (method_exists($user, 'activeSchools')) {
            return $user->activeSchools()->pluck('schools.id')->toArray();
        }

        // Fallback: Return user's school if they belong to one via school_id attribute
        if (isset($user->school_id)) {
            return [$user->school_id];
        }

        // Fallback: Check if user has school relationship
        if (method_exists($user, 'school') && $user->school) {
            return [$user->school->id];
        }

        return [];
    }

    /**
     * Automatically set up school context for a user if they don't have any
     */
    public function setupSchoolContextForUser(User $user): bool
    {
        // Check if user already has school context
        if ($this->hasSchoolContext()) {
            return true;
        }

        // Check if user has any schools associated
        if (method_exists($user, 'activeSchools') && $user->activeSchools()->count() > 0) {
            $firstSchool = $user->activeSchools()->first();
            if ($firstSchool) {
                $this->setCurrentSchool($firstSchool->id, $firstSchool->tenant_id);
                return true;
            }
        }

        // If user has tenant context, try to find or create a school for that tenant
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            $school = \App\Models\V1\SIS\School\School::where('tenant_id', $tenantId)->first();

            if ($school) {
                // Associate user with existing school
                if (!$user->schools()->where('schools.id', $school->id)->exists()) {
                    $user->schools()->attach($school->id, [
                        'role' => 'staff',
                        'status' => 'active',
                        'start_date' => now(),
                        'end_date' => null,
                        'permissions' => null,
                    ]);
                }

                $this->setCurrentSchool($school->id, $school->tenant_id);
                return true;
            }
        }

        return false;
    }

    /**
     * Get available schools for the current user with detailed information
     */
    public function getAvailableSchools(): array
    {
        if (!Auth::check()) {
            return [];
        }

        /** @var User $user */
        $user = Auth::user();

        if (method_exists($user, 'activeSchools')) {
            return $user->activeSchools()->get()->map(function ($school) {
                return [
                    'id' => $school->id,
                    'name' => $school->official_name,
                    'display_name' => $school->display_name,
                    'short_name' => $school->short_name,
                    'school_code' => $school->school_code,
                    'tenant_id' => $school->tenant_id,
                    'role' => $school->pivot->role ?? 'staff',
                    'status' => $school->pivot->status ?? 'active',
                ];
            })->toArray();
        }

        return [];
    }
}

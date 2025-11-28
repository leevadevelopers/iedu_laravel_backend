<?php

namespace App\Models;

use App\Models\Settings\Tenant;
use App\Models\Traits\TenantPermission;
use App\Models\V1\SIS\School\SchoolUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, TenantPermission, SoftDeletes {
        TenantPermission::hasPermissionTo insteadof HasRoles;
        HasRoles::hasPermissionTo as hasRolePermissionTo;
        TenantPermission::hasRole insteadof HasRoles;
        HasRoles::hasRole as hasRoleBase;
    }

    protected $guard_name = 'api';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'role_id',
        'name',
        'identifier',
        'type',
        'verified_at',
        'password',
        'must_change',
        'remember_token',
        'phone',
        'profile_photo_path',
        'is_active',
        'last_login_at',
        'settings',
        'user_type',
        'emergency_contact_json',
        'transport_notification_preferences',
        'whatsapp_phone',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'verified_at' => 'datetime',
        'must_change' => 'boolean',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'settings' => 'array',
        'emergency_contact_json' => 'array',
        'transport_notification_preferences' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['role_id', 'permissions', 'current_tenant', 'joined_at', 'status'])
            ->withTimestamps();
    }

    public function activeTenants(): BelongsToMany
    {
        return $this->tenants()
            ->where('tenants.is_active', true)
            ->wherePivot('status', 'active');
    }

    public function getCurrentTenant(): ?Tenant
    {
        // Check session first
        $tenantId = session('tenant_id');

        if ($tenantId) {
            // Use cache to avoid repeated database queries
            $cacheKey = "tenant_{$tenantId}";
            $cachedTenant = Cache::get($cacheKey);

            if ($cachedTenant !== null) {
                return $cachedTenant;
            }

            // Query database and cache result
            $tenant = $this->tenants()->find($tenantId);
            if ($tenant) {
                Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
                return $tenant;
            }
        }

        // If no session or cached tenant, query database
        $cacheKey = "user_current_tenant_{$this->id}";
        $cachedTenant = Cache::get($cacheKey);

        if ($cachedTenant !== null) {
            session(['tenant_id' => $cachedTenant->id]);
            return $cachedTenant;
        }

        // Query for current tenant
        $tenant = $this->tenants()->wherePivot('current_tenant', true)->first();

        if ($tenant) {
            session(['tenant_id' => $tenant->id]);
            Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
            return $tenant;
        }

        // Fallback to first tenant
        $tenant = $this->tenants()->first();
        if ($tenant) {
            session(['tenant_id' => $tenant->id]);
            Cache::put($cacheKey, $tenant, 300); // Cache for 5 minutes
            return $tenant;
        }

        return null;
    }

    public function switchTenant(int $tenantId): bool
    {
        if (!$this->tenants()->where('tenants.id', $tenantId)->exists()) {
            return false;
        }

        $this->tenants()->updateExistingPivot($this->tenants()->pluck('tenants.id'), [
            'current_tenant' => false
        ]);

        $this->tenants()->updateExistingPivot($tenantId, ['current_tenant' => true]);
        session(['tenant_id' => $tenantId]);

        // Clear tenant cache
        $this->clearTenantCache();

        return true;
    }

    /**
     * Clear tenant-related cache
     */
    public function clearTenantCache(): void
    {
        $oldTenantId = session('tenant_id');
        if ($oldTenantId) {
            Cache::forget("tenant_{$oldTenantId}");
        }
        Cache::forget("user_current_tenant_{$this->id}");
        Cache::forget("user_tenant_{$this->id}");
    }

    public function belongsToTenant(int $tenantId): bool
    {
        logger()->debug('User::belongsToTenant called', ['tenantId' => $tenantId]);
        return $this->tenants()
            ->where('tenants.id', $tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }



    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->whereHas('tenants', function ($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId)->wherePivot('status', 'active');
        });
    }

    /**
     * Get the schools associated with this user through the school_users pivot table.
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\V1\SIS\School\School::class, 'school_users')
            ->using(\App\Models\V1\SIS\School\SchoolUser::class)
            ->withPivot(['role', 'status', 'start_date', 'end_date', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Get the active schools for this user.
     */
    public function activeSchools()
    {
        //check all school users and get the active ones
        $schoolUsers = SchoolUser::where('user_id', $this->id)->where('status', 'active')->get();
        Log::info('School users', ['schoolUsers' => $schoolUsers]);
        return $schoolUsers;

        // return $this->schools()
        //     ->wherePivot('status', 'active')
        //     ->where(function ($query) {
        //         $query->where(function ($subQuery) {
        //             // Check that start_date is not in the future
        //             $subQuery->whereNull('school_users.start_date')
        //                      ->orWhere('school_users.start_date', '<=', now());
        //         })
        //         ->where(function ($subQuery) {
        //             // Check that end_date is not in the past
        //             $subQuery->whereNull('school_users.end_date')
        //                      ->orWhere('school_users.end_date', '>=', now());
        //         });
        //     });
    }

    /**
     * Get the current school for this user (from session or first active school).
     */
    public function getCurrentSchool(): ?\App\Models\V1\SIS\School\School
    {
        // Check session first
        $schoolId = session('current_school_id');

        if ($schoolId) {
            // Use the schools() relationship with active filter
            $school = $this->schools()
                ->wherePivot('status', 'active')
                ->where('schools.id', $schoolId)
                ->first();

            if ($school) {
                return $school;
            }
        }

        // Fallback to first active school using schools() relationship
        return $this->schools()
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                // Check that start_date is not in the future (if exists)
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('school_users.start_date')
                             ->orWhere('school_users.start_date', '<=', now());
                })
                // Check that end_date is not in the past (if exists)
                ->where(function ($subQuery) {
                    $subQuery->whereNull('school_users.end_date')
                             ->orWhere('school_users.end_date', '>=', now());
                });
            })
            ->first();
    }

    /**
     * Switch to a different school.
     */
    public function switchSchool(int $schoolId): bool
    {
        // Check if user has access to this school through schools() relationship
        $hasAccess = $this->schools()
            ->wherePivot('status', 'active')
            ->where('schools.id', $schoolId)
            ->exists();

        if (!$hasAccess) {
            return false;
        }

        session(['current_school_id' => $schoolId]);
        return true;
    }

    /**
     * Check if user is super_admin (cross-tenant role)
     * This method safely checks for super_admin role with tenant_id NULL or 0
     */
    public function isSuperAdmin(): bool
    {
        // Use cache to avoid repeated queries
        $cacheKey = "user_is_super_admin_{$this->id}";
        return Cache::remember($cacheKey, 300, function () {
            Log::info('User::isSuperAdmin - Checking super admin status', [
                'user_id' => $this->id,
                'guard_name' => $this->guard_name,
            ]);

            // Method 1: Check via hasRoleBase (Spatie base method - checks all tenants)
            if (method_exists($this, 'hasRoleBase')) {
                // hasRoleBase with null tenant_id checks cross-tenant roles
                $hasRoleBase = $this->hasRoleBase('super_admin', $this->guard_name);
                Log::info('User::isSuperAdmin - hasRoleBase check', [
                    'user_id' => $this->id,
                    'hasRoleBase' => $hasRoleBase,
                ]);
                if ($hasRoleBase) {
                    return true;
                }
            }

            // Method 2: Check via direct database query (bypass Spatie's team filtering)
            // Spatie with teams enabled filters by tenant_id automatically, so we need direct query
            $hasSuperAdmin = DB::table('model_has_roles')
                ->where('model_has_roles.model_type', get_class($this))
                ->where('model_has_roles.model_id', $this->id)
                ->where(function($q) {
                    // Check for cross-tenant role (tenant_id IS NULL or 0)
                    $q->whereNull('model_has_roles.tenant_id')
                      ->orWhere('model_has_roles.tenant_id', 0);
                })
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'super_admin')
                ->where('roles.guard_name', $this->guard_name)
                ->exists();

            Log::info('User::isSuperAdmin - direct database check', [
                'user_id' => $this->id,
                'hasSuperAdmin' => $hasSuperAdmin,
            ]);

            if ($hasSuperAdmin) {
                return true;
            }

            // Method 3: Check via getRoleNames (may include tenant filtering)
            if (method_exists($this, 'getRoleNames')) {
                $roles = $this->getRoleNames();
                Log::info('User::isSuperAdmin - getRoleNames check', [
                    'user_id' => $this->id,
                    'roles' => $roles->toArray(),
                    'contains_super_admin' => $roles->contains('super_admin'),
                ]);
                if ($roles->contains('super_admin')) {
                    // Verify it's a cross-tenant role
                    $superAdminRole = $this->roles()
                        ->where('name', 'super_admin')
                        ->where('guard_name', $this->guard_name)
                        ->where(function($q) {
                            $q->whereNull('model_has_roles.tenant_id')
                              ->orWhere('model_has_roles.tenant_id', 0);
                        })
                        ->exists();
                    if ($superAdminRole) {
                        return true;
                    }
                }
            }

            Log::info('User::isSuperAdmin - Not super admin', [
                'user_id' => $this->id,
            ]);
            return false;
        });
    }

    /**
     * Clear super admin cache
     */
    public function clearSuperAdminCache(): void
    {
        Cache::forget("user_is_super_admin_{$this->id}");
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Clear super admin cache when user is updated
        static::updated(function ($user) {
            $user->clearSuperAdminCache();
        });

        // Clear super admin cache when roles are synced
        static::saved(function ($user) {
            $user->clearSuperAdminCache();
        });
    }
}

<?php

namespace App\Models\V1\SIS\School;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * SchoolUser Model
 *
 * Pivot model for the many-to-many relationship between users and schools.
 * Represents a user's association with a specific school including their role,
 * status, and permissions.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $role
 * @property string $status
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property array|null $permissions
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class SchoolUser extends Pivot
{
    /**
     * The table associated with the model.
     */
    protected $table = 'school_users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'role',
        'status',
        'start_date',
        'end_date',
        'permissions',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'permissions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the school that owns the school user.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user that owns the school user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the school user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the school user is currently valid (active and within date range).
     */
    public function isValid(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $now = now();

        // Check if start_date is not in the future
        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        // Check if end_date is not in the past
        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the school user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->permissions) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if the school user has any of the specified permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if (!$this->permissions) {
            return false;
        }

        return !empty(array_intersect($permissions, $this->permissions));
    }

    /**
     * Check if the school user has all of the specified permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        if (!$this->permissions) {
            return false;
        }

        return empty(array_diff($permissions, $this->permissions));
    }

    /**
     * Get the role display name.
     */
    public function getRoleDisplayName(): string
    {
        return ucwords(str_replace('_', ' ', $this->role));
    }

    /**
     * Scope to filter active school users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to filter valid school users (active and within date range).
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope to filter by school.
     */
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

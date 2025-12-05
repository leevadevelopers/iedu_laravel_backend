<?php

namespace App\Models\Settings;

use App\Models\User;
use App\Models\Subscription\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'database', 'settings', 'is_active', 'created_by', 'owner_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role_id', 'permissions', 'current_tenant', 'joined_at', 'status'])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', 'active');
    }

    public function owner()
    {
        return $this->users()
            ->wherePivot('role_id', function($query) {
                $query->select('id')->from('roles')->where('name', 'owner')->limit(1);
            })
            ->first();
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function getFeatures(): array
    {
        return $this->getSetting('features', []);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('slug', 'like', "%{$search}%")
              ->orWhere('domain', 'like', "%{$search}%");
        });
    }

    // Subscription relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function getActivePackageAttribute()
    {
        return $this->activeSubscription?->package;
    }
}

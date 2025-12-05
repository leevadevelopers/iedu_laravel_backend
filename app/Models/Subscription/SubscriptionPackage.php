<?php

namespace App\Models\Subscription;

use App\Models\Subscription\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'duration_days',
        'price', 'features', 'status'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array', // Ensure features are always an array
        'status' => 'string',  // Ensure ENUM status is correctly stored
    ];

    // Relationships
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helpers
    public function getFeatureValue(string $key, $default = null)
    {
        return isset($this->features[$key]) ? $this->features[$key] : $default;
    }

    public function hasFeature(string $key): bool
    {
        return !empty($this->features) && isset($this->features[$key]);
    }
}


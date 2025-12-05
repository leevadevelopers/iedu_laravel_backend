<?php

namespace App\Models\Subscription;

use App\Models\Subscription\SubscriptionPackage;
use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'project_id', 'subscription_package_id', 'start_date',
        'end_date', 'auto_renew', 'status', 'metadata'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
        'metadata' => 'array'
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Project::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(SubscriptionExtension::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('end_date', '>', now());
    }

    // Helpers
    public function isExpired(): bool
    {
        return $this->end_date->isPast();
    }

    public function extend(int $days, string $reason = null, int $grantedBy = null)
    {
        return DB::transaction(function () use ($days, $reason, $grantedBy) {
            $originalEndDate = $this->end_date;
            $newEndDate = Carbon::parse($originalEndDate)->addDays($days);

            // Create an extension record
            $this->extensions()->create([
                'days_added' => $days,
                'original_end_date' => $originalEndDate,
                'new_end_date' => $newEndDate,
                'reason' => $reason,
                'granted_by' => $grantedBy
            ]);

            // Update subscription end date
            $this->update(['end_date' => $newEndDate]);
        });
    }
}


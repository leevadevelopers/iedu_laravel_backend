<?php

namespace App\Models\Subscription;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id', 'days_added',
        'original_end_date', 'new_end_date', 'reason', 'granted_by', 'metadata'
    ];

    protected $casts = [
        'original_end_date' => 'date',
        'new_end_date' => 'date',
        'metadata' => 'array'
    ];

    // Relationships
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}


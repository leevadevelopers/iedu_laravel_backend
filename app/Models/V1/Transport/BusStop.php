<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class BusStop extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'transport_route_id',
        'name',
        'code',
        'address',
        'latitude',
        'longitude',
        'stop_order',
        'scheduled_arrival_time',
        'scheduled_departure_time',
        'estimated_wait_minutes',
        'is_pickup_point',
        'is_dropoff_point',
        'landmarks',
        'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'scheduled_arrival_time' => 'datetime:H:i',
        'scheduled_departure_time' => 'datetime:H:i',
        'is_pickup_point' => 'boolean',
        'is_dropoff_point' => 'boolean',
        'landmarks' => 'array',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function pickupSubscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class, 'pickup_stop_id');
    }

    public function dropoffSubscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class, 'dropoff_stop_id');
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(StudentTransportEvent::class, 'bus_stop_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePickupPoints($query)
    {
        return $query->where('is_pickup_point', true);
    }

    public function scopeDropoffPoints($query)
    {
        return $query->where('is_dropoff_point', true);
    }

    // Methods
    public function getDistanceToPoint($latitude, $longitude): float
    {
        // Haversine formula to calculate distance
        $earthRadius = 6371; // km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    public function getActiveStudentCount(): int
    {
        return $this->pickupSubscriptions()
            ->where('status', 'active')
            ->count();
    }
}

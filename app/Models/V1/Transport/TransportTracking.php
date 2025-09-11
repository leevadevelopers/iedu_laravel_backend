<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportTracking extends Model
{
    use HasFactory, MultiTenant;

    protected $table = 'transport_tracking';

    protected $fillable = [
        'school_id',
        'fleet_bus_id',
        'transport_route_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'altitude',
        'tracked_at',
        'status',
        'current_stop_id',
        'next_stop_id',
        'eta_minutes',
        'raw_gps_data',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed_kmh' => 'decimal:2',
        'altitude' => 'decimal:2',
        'tracked_at' => 'datetime',
        'raw_gps_data' => 'array',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function fleetBus(): BelongsTo
    {
        return $this->belongsTo(FleetBus::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function currentStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'current_stop_id');
    }

    public function nextStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'next_stop_id');
    }

    // Scopes
    public function scopeLatest($query)
    {
        return $query->orderByDesc('tracked_at');
    }

    public function scopeForBus($query, $busId)
    {
        return $query->where('fleet_bus_id', $busId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('tracked_at', now()->toDateString());
    }

    // Methods
    public function getSpeedFormatted(): string
    {
        return number_format($this->speed_kmh, 1) . ' km/h';
    }

    public function getEtaFormatted(): string
    {
        if (!$this->eta_minutes) return 'N/A';

        $hours = intval($this->eta_minutes / 60);
        $minutes = $this->eta_minutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'min';
        }

        return $minutes . 'min';
    }

    public function isStationary(): bool
    {
        return $this->speed_kmh < 1;
    }

    public function isMoving(): bool
    {
        return $this->speed_kmh >= 1;
    }

    public function getCoordinates(): array
    {
        return [
            'lat' => (float) $this->latitude,
            'lng' => (float) $this->longitude,
        ];
    }
}

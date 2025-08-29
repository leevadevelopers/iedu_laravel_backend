<?php

namespace App\Models\V1\Transport;

use App\Models\User;
use App\Models\V1\SIS\School\School;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class BusRouteAssignment extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'fleet_bus_id',
        'transport_route_id',
        'driver_id',
        'assistant_id',
        'assigned_date',
        'valid_until',
        'status',
        'notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
        'valid_until' => 'date',
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->whereDate('assigned_date', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('valid_until')
                          ->orWhereDate('valid_until', '>=', now());
                    });
    }

    public function scopeCurrent($query)
    {
        return $query->active();
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->assigned_date <= now() &&
               ($this->valid_until === null || $this->valid_until >= now());
    }

    public function getDurationDays(): int
    {
        $start = $this->assigned_date;
        $end = $this->valid_until ?? now();
        return $start->diffInDays($end);
    }
}

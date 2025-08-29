<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class FleetBus extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'license_plate',
        'internal_code',
        'make',
        'model',
        'manufacture_year',
        'capacity',
        'current_capacity',
        'fuel_type',
        'fuel_consumption_per_km',
        'gps_device_id',
        'safety_features',
        'last_inspection_date',
        'next_inspection_due',
        'insurance_expiry',
        'status',
        'notes',
    ];

    protected $casts = [
        'fuel_consumption_per_km' => 'decimal:2',
        'safety_features' => 'array',
        'last_inspection_date' => 'date',
        'next_inspection_due' => 'date',
        'insurance_expiry' => 'date',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function routeAssignments(): HasMany
    {
        return $this->hasMany(BusRouteAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(BusRouteAssignment::class)
            ->where('status', 'active')
            ->whereDate('assigned_date', '<=', now())
            ->where(function($query) {
                $query->whereNull('valid_until')
                      ->orWhereDate('valid_until', '>=', now());
            });
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(StudentTransportEvent::class);
    }

    public function transportTracking(): HasMany
    {
        return $this->hasMany(TransportTracking::class);
    }

    public function latestTracking(): HasOne
    {
        return $this->hasOne(TransportTracking::class)
            ->orderByDesc('tracked_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(TransportIncident::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(TransportDailyLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
                    ->doesntHave('currentAssignment');
    }

    public function scopeNeedingInspection($query)
    {
        return $query->where('next_inspection_due', '<=', now()->addDays(30));
    }

    // Methods
    public function getCurrentRoute()
    {
        return $this->currentAssignment?->transportRoute;
    }

    public function getCurrentDriver()
    {
        return $this->currentAssignment?->driver;
    }

    public function getCurrentAssistant()
    {
        return $this->currentAssignment?->assistant;
    }

    public function getLastKnownLocation()
    {
        return $this->latestTracking;
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active' && !$this->currentAssignment;
    }

    public function getUtilizationRate(): float
    {
        if ($this->capacity === 0) return 0;
        return ($this->current_capacity / $this->capacity) * 100;
    }

    public function needsInspection(): bool
    {
        return $this->next_inspection_due &&
               $this->next_inspection_due <= now()->addDays(30);
    }
}

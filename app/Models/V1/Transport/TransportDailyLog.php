<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\User;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class TransportDailyLog extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'fleet_bus_id',
        'transport_route_id',
        'driver_id',
        'assistant_id',
        'log_date',
        'shift',
        'departure_time',
        'arrival_time',
        'students_picked_up',
        'students_dropped_off',
        'fuel_level_start',
        'fuel_level_end',
        'odometer_start',
        'odometer_end',
        'safety_checklist',
        'notes',
        'status',
    ];

    protected $casts = [
        'log_date' => 'date',
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
        'fuel_level_start' => 'decimal:2',
        'fuel_level_end' => 'decimal:2',
        'safety_checklist' => 'array',
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
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('log_date', now()->toDateString());
    }

    public function scopeForShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }

    // Methods
    public function getTripDuration(): ?int
    {
        if (!$this->departure_time || !$this->arrival_time) {
            return null;
        }

        return $this->departure_time->diffInMinutes($this->arrival_time);
    }

    public function getFuelConsumed(): ?float
    {
        if (!$this->fuel_level_start || !$this->fuel_level_end) {
            return null;
        }

        return $this->fuel_level_start - $this->fuel_level_end;
    }

    public function getDistanceTraveled(): ?int
    {
        if (!$this->odometer_start || !$this->odometer_end) {
            return null;
        }

        return $this->odometer_end - $this->odometer_start;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed' &&
               $this->departure_time &&
               $this->arrival_time;
    }

    public function getSafetyChecklistCompletion(): float
    {
        if (!$this->safety_checklist) return 0;

        $total = count($this->safety_checklist);
        $completed = count(array_filter($this->safety_checklist));

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }
}

<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\BusRouteAssignment;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Models\V1\Transport\TransportDailyLog;
use App\Models\V1\Transport\TransportIncident;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class TransportRoute extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'waypoints',
        'departure_time',
        'arrival_time',
        'estimated_duration_minutes',
        'total_distance_km',
        'status',
        'shift',
        'operating_days',
    ];

    protected $casts = [
        'waypoints' => 'array',
        'operating_days' => 'array',
        'departure_time' => 'datetime:H:i',
        'arrival_time' => 'datetime:H:i',
        'total_distance_km' => 'decimal:2',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function busStops(): HasMany
    {
        return $this->hasMany(BusStop::class)->orderBy('stop_order');
    }

    public function busAssignments(): HasMany
    {
        return $this->hasMany(BusRouteAssignment::class);
    }

    public function studentSubscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class);
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(StudentTransportEvent::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(TransportDailyLog::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(TransportIncident::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }

    public function scopeOperatingToday($query)
    {
        $today = strtolower(now()->format('l'));
        return $query->whereJsonContains('operating_days', $today);
    }

    // Methods
    public function getCurrentBus()
    {
        return $this->busAssignments()
            ->where('status', 'active')
            ->whereDate('assigned_date', '<=', now())
            ->where(function($query) {
                $query->whereNull('valid_until')
                      ->orWhereDate('valid_until', '>=', now());
            })
            ->first()?->fleetBus;
    }

    public function getActiveStudentCount(): int
    {
        return $this->studentSubscriptions()
            ->where('status', 'active')
            ->count();
    }

    public function isOperatingToday(): bool
    {
        $today = strtolower(now()->format('l'));
        return in_array($today, $this->operating_days ?? []);
    }
}

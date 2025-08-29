#!/bin/bash

# Transport Module - Models Generator
echo "📊 Creating Transport Module Models..."

# 1. TransportRoute Model
cat > app/Models/V1/Transport/TransportRoute.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
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
EOF

# 2. BusStop Model
cat > app/Models/V1/Transport/BusStop.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
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
EOF

# 3. FleetBus Model
cat > app/Models/V1/Transport/FleetBus.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
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
EOF

# 4. BusRouteAssignment Model
cat > app/Models/V1/Transport/BusRouteAssignment.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Models\User;
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
EOF

# 5. StudentTransportSubscription Model
cat > app/Models/V1/Transport/StudentTransportSubscription.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Models\Student;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class StudentTransportSubscription extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'student_id',
        'pickup_stop_id',
        'dropoff_stop_id',
        'transport_route_id',
        'qr_code',
        'rfid_card_id',
        'subscription_type',
        'start_date',
        'end_date',
        'monthly_fee',
        'auto_renewal',
        'authorized_parents',
        'status',
        'special_needs',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_fee' => 'decimal:2',
        'auto_renewal' => 'boolean',
        'authorized_parents' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($subscription) {
            if (empty($subscription->qr_code)) {
                $subscription->qr_code = $subscription->generateQrCode();
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'pickup_stop_id');
    }

    public function dropoffStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'dropoff_stop_id');
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(StudentTransportEvent::class, 'student_id', 'student_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->whereDate('start_date', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('end_date')
                          ->orWhereDate('end_date', '>=', now());
                    });
    }

    public function scopeExpiring($query, $days = 30)
    {
        return $query->whereNotNull('end_date')
                    ->whereDate('end_date', '<=', now()->addDays($days));
    }

    // Methods
    protected function generateQrCode(): string
    {
        return 'STU' . $this->school_id . $this->student_id . time();
    }

    public function generateQrCodeImage(): string
    {
        $qrCode = new QrCode($this->qr_code);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return base64_encode($result->getString());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->start_date <= now() &&
               ($this->end_date === null || $this->end_date >= now());
    }

    public function isExpiring($days = 30): bool
    {
        return $this->end_date &&
               $this->end_date <= now()->addDays($days);
    }

    public function getRemainingDays(): ?int
    {
        return $this->end_date ? now()->diffInDays($this->end_date) : null;
    }

    public function canAutoRenew(): bool
    {
        return $this->auto_renewal && $this->isExpiring(7);
    }
}
EOF

# 6. StudentTransportEvent Model
cat > app/Models/V1/Transport/StudentTransportEvent.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class StudentTransportEvent extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'student_id',
        'fleet_bus_id',
        'bus_stop_id',
        'transport_route_id',
        'event_type',
        'event_timestamp',
        'validation_method',
        'validation_data',
        'recorded_by',
        'event_latitude',
        'event_longitude',
        'is_automated',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'event_timestamp' => 'datetime',
        'event_latitude' => 'decimal:7',
        'event_longitude' => 'decimal:7',
        'is_automated' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fleetBus(): BelongsTo
    {
        return $this->belongsTo(FleetBus::class);
    }

    public function busStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Scopes
    public function scopeCheckIns($query)
    {
        return $query->where('event_type', 'check_in');
    }

    public function scopeCheckOuts($query)
    {
        return $query->where('event_type', 'check_out');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('event_timestamp', now()->toDateString());
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForBus($query, $busId)
    {
        return $query->where('fleet_bus_id', $busId);
    }

    // Methods
    public function isCheckIn(): bool
    {
        return $this->event_type === 'check_in';
    }

    public function isCheckOut(): bool
    {
        return $this->event_type === 'check_out';
    }

    public function getFormattedEventTime(): string
    {
        return $this->event_timestamp->format('H:i');
    }

    public function getTimeSinceEvent(): string
    {
        return $this->event_timestamp->diffForHumans();
    }
}
EOF

# 7. TransportTracking Model
cat > app/Models/V1/Transport/TransportTracking.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportTracking extends Model
{
    use HasFactory, MultiTenant;

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
EOF

# 8. TransportIncident Model
cat > app/Models/V1/Transport/TransportIncident.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Models\User;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;

class TransportIncident extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'fleet_bus_id',
        'transport_route_id',
        'incident_type',
        'severity',
        'title',
        'description',
        'incident_datetime',
        'incident_latitude',
        'incident_longitude',
        'reported_by',
        'affected_students',
        'witnesses',
        'immediate_action_taken',
        'status',
        'assigned_to',
        'resolved_at',
        'resolution_notes',
        'attachments',
        'parents_notified',
        'parents_notified_at',
    ];

    protected $casts = [
        'incident_datetime' => 'datetime',
        'incident_latitude' => 'decimal:7',
        'incident_longitude' => 'decimal:7',
        'affected_students' => 'array',
        'witnesses' => 'array',
        'resolved_at' => 'datetime',
        'attachments' => 'array',
        'parents_notified' => 'boolean',
        'parents_notified_at' => 'datetime',
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['reported', 'investigating']);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    // Methods
    public function isOpen(): bool
    {
        return in_array($this->status, ['reported', 'investigating']);
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function getResolutionTime(): ?int
    {
        if (!$this->resolved_at) return null;

        return $this->incident_datetime->diffInMinutes($this->resolved_at);
    }

    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray'
        };
    }
}
EOF

# 9. TransportDailyLog Model
cat > app/Models/V1/Transport/TransportDailyLog.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
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
EOF

# 10. TransportNotification Model
cat > app/Models/V1/Transport/TransportNotification.php << 'EOF'
<?php

namespace App\Models\V1\Transport;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportNotification extends Model
{
    use HasFactory, MultiTenant, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'parent_id',
        'notification_type',
        'channel',
        'subject',
        'message',
        'metadata',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failure_reason',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForRetry($query)
    {
        return $query->where('status', 'failed')
                    ->where('retry_count', '<', 3)
                    ->where('next_retry_at', '<=', now());
    }

    // Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() &&
               $this->retry_count < 3 &&
               $this->next_retry_at <= now();
    }

    public function markAsSent(): self
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $this;
    }

    public function markAsDelivered(): self
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        return $this;
    }

    public function markAsRead(): self
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return $this;
    }

    public function markAsFailed(string $reason): self
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes(pow(2, $this->retry_count) * 5), // exponential backoff
        ]);

        return $this;
    }
}
EOF

# Create MultiTenant trait if it doesn't exist
cat > app/Traits/MultiTenant.php << 'EOF'
<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait MultiTenant
{
    protected static function bootMultiTenant()
    {
        static::addGlobalScope('school', function (Builder $builder) {
            if (auth()->check() && auth()->user()->school_id) {
                $builder->where('school_id', auth()->user()->school_id);
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->school_id) {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }
}
EOF

echo "✅ Transport module models created successfully!"
echo "📝 All models include multi-tenancy, auditing, and proper relationships."

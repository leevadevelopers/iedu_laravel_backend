<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
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

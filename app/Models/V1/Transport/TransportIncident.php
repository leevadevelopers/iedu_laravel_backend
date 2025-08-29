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

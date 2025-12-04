<?php

namespace App\Models;

use App\Models\Settings\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected $fillable = [
        'log_name', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id',
        'properties', 'tenant_id', 'event', 'batch_uuid', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, $tenantId = null)
    {
        // If tenantId is null (for super_admin), don't filter by tenant (show all logs)
        // If tenantId is an integer, filter by that tenant
        // Legacy: if not provided, use session as fallback
        if ($tenantId === null && func_num_args() < 2) {
            // tenantId not provided, use session fallback for legacy support
            $tenantId = session('tenant_id');
        }
        
        // Filter by tenant if tenantId is set, otherwise return all (for super_admin)
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    public function scopeInLog(\Illuminate\Database\Eloquent\Builder $query, ...$logNames): \Illuminate\Database\Eloquent\Builder
    {
        $logNames = is_array($logNames[0]) ? $logNames[0] : $logNames;
        return $query->whereIn('log_name', $logNames);
    }

    public function scopeByEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    public function scopeInBatch($query, $batchUuid)
    {
        return $query->where('batch_uuid', $batchUuid);
    }

    public function getHumanDescriptionAttribute(): string
    {
        $causer = $this->causer;
        $subject = $this->subject;
        
        $causerName = $causer ? $causer->name ?? $causer->email ?? 'System' : 'System';
        $subjectName = $subject ? $subject->name ?? $subject->title ?? class_basename($subject) : 'Unknown';
        
        return "{$causerName} {$this->description} {$subjectName}";
    }

    public function getMetadataAttribute(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'event' => $this->event,
            'batch_uuid' => $this->batch_uuid,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
        ];
    }
}
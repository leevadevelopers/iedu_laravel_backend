<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ScheduleConflict extends BaseModel
{
    use Tenantable;

    protected $fillable = [
        'tenant_id', 'school_id', 'conflict_type', 'conflict_description',
        'conflicting_schedule_ids', 'affected_entities',
        'conflict_date', 'conflict_start_time', 'conflict_end_time', 'severity',
        'status', 'resolution_notes', 'resolved_by', 'resolved_at',
        'detection_method', 'detected_by'
    ];

    protected $casts = [
        'conflicting_schedule_ids' => 'array',
        'affected_entities' => 'array',
        'conflict_date' => 'date',
        'conflict_start_time' => 'datetime:H:i:s',
        'conflict_end_time' => 'datetime:H:i:s',
        'resolved_at' => 'datetime'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function detectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by');
    }

    // Scopes
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['detected', 'acknowledged']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('severity', 'high');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('conflict_type', $type);
    }

    // Methods
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function resolve(int $userId, string $notes): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ]);
    }

    public function acknowledge(): bool
    {
        return $this->update(['status' => 'acknowledged']);
    }

    public function ignore(): bool
    {
        return $this->update(['status' => 'ignored']);
    }

    public function getConflictingSchedules()
    {
        if (empty($this->conflicting_schedule_ids)) {
            return collect();
        }

        return Schedule::whereIn('id', $this->conflicting_schedule_ids)
                      ->with(['teacher', 'subject', 'class'])
                      ->get();
    }

    public function getSeverityColorAttribute(): string
    {
        $colors = [
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red'
        ];
        return $colors[$this->severity] ?? 'gray';
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'teacher_double_booking' => 'Conflito de Horário do Professor',
            'classroom_double_booking' => 'Conflito de Sala de Aula',
            'student_schedule_overlap' => 'Sobreposição de Horário do Aluno',
            'resource_conflict' => 'Conflito de Recurso',
            'time_constraint_violation' => 'Violação de Restrição de Tempo',
            'capacity_exceeded' => 'Capacidade Excedida'
        ];
        return $labels[$this->conflict_type] ?? ucfirst($this->conflict_type);
    }

    public function getSeverityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
            'critical' => 'Crítica'
        ];
        return $labels[$this->severity] ?? ucfirst($this->severity);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'detected' => 'Detectado',
            'acknowledged' => 'Reconhecido',
            'resolved' => 'Resolvido',
            'ignored' => 'Ignorado'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }
}

<?php

namespace App\Models\SchoolEntities;

use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\Forms\FormInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $fillable = [
        'tenant_id',
        'class_name',
        'class_code',
        'grade_level',
        'academic_year',
        'teacher_id',
        'room_number',
        'capacity',
        'current_enrollment',
        'schedule',
        'subjects',
        'description',
        'is_active',
        'metadata',
        'created_by'
    ];

    protected $casts = [
        'schedule' => 'array',
        'subjects' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function formInstances(): HasMany
    {
        return $this->hasMany(FormInstance::class, 'reference_id')
                    ->where('reference_type', 'class');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByGradeLevel($query, string $gradeLevel)
    {
        return $query->where('grade_level', $gradeLevel);
    }

    public function scopeByAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    // Helper Methods
    public function getAvailableSpotsAttribute(): int
    {
        return max(0, $this->capacity - $this->current_enrollment);
    }

    public function isFull(): bool
    {
        return $this->current_enrollment >= $this->capacity;
    }

    public function canEnrollStudent(): bool
    {
        return $this->is_active && !$this->isFull();
    }
}

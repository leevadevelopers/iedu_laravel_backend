<?php

namespace App\Models\SchoolEntities;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\Forms\FormInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $fillable = [
        'tenant_id',
        'student_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'enrollment_date',
        'graduation_date',
        'status', // active, inactive, graduated, transferred, suspended
        'grade_level',
        'class_id',
        'parent_id',
        'academic_year',
        'metadata',
        'created_by'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'enrollment_date' => 'date',
        'graduation_date' => 'date',
        'metadata' => 'array',
    ];

    // Relationships
    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StudentParent::class, 'parent_id');
    }

    public function formInstances(): HasMany
    {
        return $this->hasMany(FormInstance::class, 'reference_id')
                    ->where('reference_type', 'student');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByGradeLevel($query, string $gradeLevel)
    {
        return $query->where('grade_level', $gradeLevel);
    }

    public function scopeByClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    // Helper Methods
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isGraduated(): bool
    {
        return $this->status === 'graduated';
    }
}

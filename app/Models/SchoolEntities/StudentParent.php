<?php

namespace App\Models\SchoolEntities;

use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\Forms\FormInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentParent extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $table = 'student_parents';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'occupation',
        'employer',
        'emergency_contact',
        'relationship_type', // father, mother, guardian, other
        'is_primary_contact',
        'can_pickup',
        'communication_preferences',
        'metadata',
        'created_by'
    ];

    protected $casts = [
        'is_primary_contact' => 'boolean',
        'can_pickup' => 'boolean',
        'communication_preferences' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function formInstances(): HasMany
    {
        return $this->hasMany(FormInstance::class, 'reference_id')
                    ->where('reference_type', 'parent');
    }

    // Scopes
    public function scopePrimaryContact($query)
    {
        return $query->where('is_primary_contact', true);
    }

    public function scopeCanPickup($query)
    {
        return $query->where('can_pickup', true);
    }

    // Helper Methods
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isPrimaryContact(): bool
    {
        return $this->is_primary_contact;
    }

    public function canPickupStudent(): bool
    {
        return $this->can_pickup;
    }
}

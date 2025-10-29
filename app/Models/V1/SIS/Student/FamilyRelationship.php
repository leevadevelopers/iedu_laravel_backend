<?php

namespace App\Models\V1\SIS\Student;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Family Relationship Model
 *
 * Represents the relationship between a student and their family members/guardians
 * with specific permissions and legal responsibilities.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $guardian_user_id
 * @property string $relationship_type
 * @property string|null $relationship_description
 * @property bool $primary_contact
 * @property bool $emergency_contact
 * @property bool $pickup_authorized
 * @property bool $academic_access
 * @property bool $medical_access
 * @property bool $custody_rights
 * @property array|null $custody_details_json
 * @property bool $financial_responsibility
 * @property array|null $communication_preferences_json
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class FamilyRelationship extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'family_relationships';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'guardian_user_id',
        'relationship_type',
        'relationship_description',
        'primary_contact',
        'emergency_contact',
        'pickup_authorized',
        'academic_access',
        'medical_access',
        'custody_rights',
        'custody_details_json',
        'financial_responsibility',
        'communication_preferences_json',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'primary_contact' => 'boolean',
        'emergency_contact' => 'boolean',
        'pickup_authorized' => 'boolean',
        'academic_access' => 'boolean',
        'medical_access' => 'boolean',
        'custody_rights' => 'boolean',
        'financial_responsibility' => 'boolean',
        'custody_details_json' => 'array',
        'communication_preferences_json' => 'array',
    ];

    /**
     * Get the school that owns the relationship.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get tenant_id accessor through school relationship.
     * This is needed for WorkflowService which expects tenant_id on the model.
     */
    public function getTenantIdAttribute(): ?int
    {
        // If school relationship is loaded, use it
        if ($this->relationLoaded('school') && $this->school) {
            return $this->school->tenant_id;
        }
        
        // Otherwise, load it dynamically if we have school_id
        if ($this->school_id) {
            $school = $this->school()->first();
            return $school?->tenant_id;
        }
        
        return null;
    }

    /**
     * Get the student in the relationship.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the guardian/family member in the relationship.
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /**
     * Alias for guardian relationship (used by some controllers).
     */
    public function relatedPerson(): BelongsTo
    {
        return $this->guardian();
    }

    /**
     * Check if this is a primary contact.
     */
    public function isPrimaryContact(): bool
    {
        return $this->primary_contact;
    }

    /**
     * Check if authorized for student pickup.
     */
    public function canPickupStudent(): bool
    {
        return $this->pickup_authorized && $this->status === 'active';
    }

    /**
     * Check if has academic information access.
     */
    public function hasAcademicAccess(): bool
    {
        return $this->academic_access && $this->status === 'active';
    }

    /**
     * Check if has medical information access.
     */
    public function hasMedicalAccess(): bool
    {
        return $this->medical_access && $this->status === 'active';
    }

    /**
     * Get formatted relationship type.
     */
    public function getFormattedRelationshipType(): string
    {
        return ucwords(str_replace('_', ' ', $this->relationship_type));
    }

    /**
     * Scope to filter active relationships.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter primary contacts.
     */
    public function scopePrimary($query)
    {
        return $query->where('primary_contact', true);
    }

    /**
     * Scope to filter emergency contacts.
     */
    public function scopeEmergency($query)
    {
        return $query->where('emergency_contact', true);
    }
}

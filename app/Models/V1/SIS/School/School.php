<?php

namespace App\Models\V1\SIS\School;

use App\Models\BaseModel;
use App\Models\Settings\Tenant;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\School\SchoolEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * School Model
 *
 * Multi-tenant root for educational institutions with comprehensive
 * configuration and operational settings.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $school_code
 * @property string $official_name
 * @property string $display_name
 * @property string $short_name
 * @property string $school_type
 * @property array $educational_levels
 * @property string $grade_range_min
 * @property string $grade_range_max
 * @property string $email
 * @property string|null $phone
 * @property string|null $website
 * @property array|null $address_json
 * @property string $country_code
 * @property string|null $state_province
 * @property string $city
 * @property string $timezone
 * @property string|null $ministry_education_code
 * @property string $accreditation_status
 * @property string $academic_calendar_type
 * @property int $academic_year_start_month
 * @property string $grading_system
 * @property string $attendance_tracking_level
 * @property string|null $educational_philosophy
 * @property array $language_instruction
 * @property string|null $religious_affiliation
 * @property int|null $student_capacity
 * @property int $current_enrollment
 * @property int $staff_count
 * @property string $subscription_plan
 * @property array $feature_flags
 * @property array $integration_settings
 * @property array $branding_configuration
 * @property string $status
 * @property \Carbon\Carbon|null $established_date
 * @property \Carbon\Carbon|null $onboarding_completed_at
 * @property \Carbon\Carbon|null $trial_ends_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class School extends Model
{
    use HasFactory, Tenantable, LogsActivityWithTenant;

    /**
     * The table associated with the model.
     */
    protected $table = 'schools';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'school_code',
        'official_name',
        'display_name',
        'short_name',
        'school_type',
        'educational_levels',
        'grade_range_min',
        'grade_range_max',
        'email',
        'phone',
        'website',
        'address_json',
        'country_code',
        'state_province',
        'city',
        'timezone',
        'ministry_education_code',
        'accreditation_status',
        'academic_calendar_type',
        'academic_year_start_month',
        'grading_system',
        'attendance_tracking_level',
        'educational_philosophy',
        'language_instruction',
        'religious_affiliation',
        'student_capacity',
        'current_enrollment',
        'staff_count',
        'subscription_plan',
        'feature_flags',
        'integration_settings',
        'branding_configuration',
        'status',
        'established_date',
        'onboarding_completed_at',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'educational_levels' => 'array',
        'address_json' => 'array',
        'language_instruction' => 'array',
        'feature_flags' => 'array',
        'integration_settings' => 'array',
        'branding_configuration' => 'array',
        'student_capacity' => 'integer',
        'current_enrollment' => 'integer',
        'staff_count' => 'integer',
        'academic_year_start_month' => 'integer',
        'established_date' => 'date',
        'onboarding_completed_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the school.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the users associated with this school through the school_users pivot table.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_users')
            ->using(SchoolUser::class)
            ->withPivot(['role', 'status', 'start_date', 'end_date', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Get the active users for this school.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->whereNull('school_users.end_date')
                      ->orWhere('school_users.end_date', '>=', now());
            });
    }

    /**
     * Get the students associated with this school.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Get the academic years for this school.
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * Get the current active academic year.
     */
    public function currentAcademicYear(): HasOne
    {
        return $this->hasOne(AcademicYear::class)->where('is_current', true);
    }

    /**
     * Get the family relationships associated with this school.
     */
    public function familyRelationships(): HasMany
    {
        return $this->hasMany(FamilyRelationship::class);
    }

    /**
     * Get the student documents associated with this school.
     */
    public function studentDocuments(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * Get the student enrollment history for this school.
     */
    public function studentEnrollmentHistory(): HasMany
    {
        return $this->hasMany(StudentEnrollmentHistory::class);
    }

    /**
     * Get the form instances associated with this school.
     */
    public function formInstances(): HasMany
    {
        return $this->hasMany(\App\Models\Forms\FormInstance::class, 'reference_id')
            ->where('reference_type', 'School');
    }

    /**
     * Get the events associated with this school.
     */
    public function events(): HasMany
    {
        return $this->hasMany(SchoolEvent::class);
    }

    /**
     * Check if the school is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the school is in setup mode.
     */
    public function isInSetup(): bool
    {
        return $this->status === 'setup';
    }

    /**
     * Check if the school is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if onboarding is completed.
     */
    public function isOnboardingCompleted(): bool
    {
        return !is_null($this->onboarding_completed_at);
    }

    /**
     * Get the school's full address.
     */
    public function getFullAddress(): string
    {
        if (!$this->address_json) {
            return $this->city . ', ' . $this->state_province . ', ' . $this->country_code;
        }

        $address = $this->address_json;
        $parts = [];

        if (isset($address['street'])) $parts[] = $address['street'];
        if (isset($address['city'])) $parts[] = $address['city'];
        if (isset($address['state'])) $parts[] = $address['state'];
        if (isset($address['postal_code'])) $parts[] = $address['postal_code'];
        if (isset($address['country'])) $parts[] = $address['country'];

        return implode(', ', $parts);
    }

    /**
     * Get formatted school type.
     */
    public function getFormattedSchoolType(): string
    {
        return ucwords(str_replace('_', ' ', $this->school_type));
    }

    /**
     * Get enrollment percentage.
     */
    public function getEnrollmentPercentage(): ?float
    {
        if (!$this->student_capacity) {
            return null;
        }

        return round(($this->current_enrollment / $this->student_capacity) * 100, 2);
    }

    /**
     * Scope to filter active schools.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by school type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('school_type', $type);
    }

    /**
     * Scope to filter by country.
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope to filter by accreditation status.
     */
    public function scopeByAccreditation($query, string $status)
    {
        return $query->where('accreditation_status', $status);
    }

    /**
     * Scope to filter schools with capacity.
     */
    public function scopeWithCapacity($query)
    {
        return $query->whereNotNull('student_capacity')
                    ->where('current_enrollment', '<', 'student_capacity');
    }
}

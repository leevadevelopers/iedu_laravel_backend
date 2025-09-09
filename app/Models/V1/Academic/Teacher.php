<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArray;
use Illuminate\Support\Carbon;

class Teacher extends BaseModel
{
    protected $fillable = [
        'school_id',
        'user_id',
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'title',
        'date_of_birth',
        'gender',
        'nationality',
        'phone',
        'email',
        'address_json',
        'employment_type',
        'hire_date',
        'termination_date',
        'status',
        'education_json',
        'certifications_json',
        'specializations_json',
        'department',
        'position',
        'salary',
        'schedule_json',
        'emergency_contacts_json',
        'bio',
        'profile_photo_path',
        'preferences_json'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'address_json' => 'array',
        'education_json' => 'array',
        'certifications_json' => 'array',
        'specializations_json' => 'array',
        'schedule_json' => 'array',
        'emergency_contacts_json' => 'array',
        'preferences_json' => 'array',
        'salary' => 'decimal:2'
    ];

    protected $hidden = [
        'salary'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'primary_teacher_id');
    }

    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'entered_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByDepartment(Builder $query, string $department): Builder
    {
        return $query->where('department', $department);
    }

    public function scopeByEmploymentType(Builder $query, string $type): Builder
    {
        return $query->where('employment_type', $type);
    }

    public function scopeBySpecialization(Builder $query, string $specialization): Builder
    {
        return $query->whereJsonContains('specializations_json', $specialization);
    }

    public function scopeByGradeLevel(Builder $query, string $gradeLevel): Builder
    {
        return $query->whereJsonContains('specializations_json', $gradeLevel);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->preferred_name ?: $this->full_name;
    }

    public function getFormalNameAttribute(): string
    {
        $title = $this->title ? $this->title . ' ' : '';
        return $title . $this->full_name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->diffInYears(now()) : null;
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFullTime(): bool
    {
        return $this->employment_type === 'full_time';
    }

    public function isPartTime(): bool
    {
        return $this->employment_type === 'part_time';
    }

    public function isSubstitute(): bool
    {
        return $this->employment_type === 'substitute';
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    public function isOnLeave(): bool
    {
        return $this->status === 'on_leave';
    }

    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specializations_json ?? []);
    }

    public function supportsGradeLevel(string $gradeLevel): bool
    {
        return $this->hasSpecialization($gradeLevel);
    }

    public function getYearsOfService(): int
    {
        return $this->hire_date ? Carbon::parse($this->hire_date)->diffInYears(now()) : 0;
    }

    public function getPrimaryEmergencyContact(): ?array
    {
        $contacts = $this->emergency_contacts_json ?? [];

        foreach ($contacts as $contact) {
            if (isset($contact['is_primary']) && $contact['is_primary']) {
                return $contact;
            }
        }

        return $contacts[0] ?? null;
    }

    public function getCurrentClasses(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->classes()->active()->get();
    }

    public function getTotalStudents(): int
    {
        return $this->classes()->active()->sum('current_enrollment');
    }

    public function canTeachSubject(string $subject): bool
    {
        return $this->hasSpecialization($subject);
    }

    public function getWorkload(): array
    {
        $classes = $this->getCurrentClasses();

        return [
            'total_classes' => $classes->count(),
            'total_students' => $this->getTotalStudents(),
            'average_class_size' => $classes->count() > 0 ? round($this->getTotalStudents() / $classes->count(), 2) : 0
        ];
    }

    public function getTeachingSchedule(): array
    {
        return $this->schedule_json ?? [];
    }

    public function isAvailableAt(string $day, string $time): bool
    {
        $schedule = $this->getTeachingSchedule();

        if (!isset($schedule[$day])) {
            return false;
        }

        $daySchedule = $schedule[$day];
        return in_array($time, $daySchedule['available_times'] ?? []);
    }

    public function getFormattedAddress(): string
    {
        $address = $this->address_json ?? [];

        if (empty($address)) {
            return '';
        }

        $parts = array_filter([
            $address['street'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
            $address['country'] ?? ''
        ]);

        return implode(', ', $parts);
    }

    public function getEducationSummary(): array
    {
        $education = $this->education_json ?? [];

        return array_map(function ($degree) {
            return [
                'degree' => $degree['degree'] ?? '',
                'field' => $degree['field'] ?? '',
                'institution' => $degree['institution'] ?? '',
                'year' => $degree['year'] ?? '',
                'gpa' => $degree['gpa'] ?? null
            ];
        }, $education);
    }

    public function getCertifications(): array
    {
        return $this->certifications_json ?? [];
    }

    public function hasValidCertification(string $certificationType): bool
    {
        $certifications = $this->getCertifications();

        foreach ($certifications as $cert) {
            if (($cert['type'] ?? '') === $certificationType) {
                $expiryDate = $cert['expiry_date'] ?? null;
                if (!$expiryDate || Carbon::parse($expiryDate)->isFuture()) {
                    return true;
                }
            }
        }

        return false;
    }
}

<?php

namespace App\Models\V1\SIS\Student;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Student Model
 *
 * Represents a student in the educational system with comprehensive
 * academic, personal, and administrative information.
 *
 * @property int $id
 * @property int $school_id
 * @property string $student_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string $date_of_birth
 * @property string|null $birth_place
 * @property string|null $gender
 * @property string|null $nationality
 * @property string|null $email
 * @property string|null $phone
 * @property array|null $address_json
 * @property string $admission_date
 * @property string $current_grade_level
 * @property int|null $current_academic_year_id
 * @property string $enrollment_status
 * @property string|null $expected_graduation_date
 * @property array|null $learning_profile_json
 * @property array|null $accommodation_needs_json
 * @property array|null $language_profile_json
 * @property array|null $medical_information_json
 * @property array|null $emergency_contacts_json
 * @property array|null $special_circumstances_json
 * @property float|null $current_gpa
 * @property float|null $attendance_rate
 * @property int $behavioral_points
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Student extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'school_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'birth_place',
        'gender',
        'nationality',
        'email',
        'phone',
        'address_json',
        'admission_date',
        'current_grade_level',
        'current_academic_year_id',
        'enrollment_status',
        'expected_graduation_date',
        'learning_profile_json',
        'accommodation_needs_json',
        'language_profile_json',
        'medical_information_json',
        'emergency_contacts_json',
        'special_circumstances_json',
        'current_gpa',
        'attendance_rate',
        'behavioral_points',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'expected_graduation_date' => 'date',
        'address_json' => 'array',
        'learning_profile_json' => 'array',
        'accommodation_needs_json' => 'array',
        'language_profile_json' => 'array',
        'medical_information_json' => 'array',
        'emergency_contacts_json' => 'array',
        'special_circumstances_json' => 'array',
        'current_gpa' => 'decimal:2',
        'attendance_rate' => 'decimal:2',
        'behavioral_points' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'medical_information_json',
        'special_circumstances_json',
    ];

    /**
     * Get the user account associated with the student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the school that owns the student.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the current academic year for the student.
     */
    public function currentAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'current_academic_year_id');
    }

    /**
     * Get the family relationships for the student.
     */
    public function familyRelationships(): HasMany
    {
        return $this->hasMany(FamilyRelationship::class);
    }

    /**
     * Get the enrollment history for the student.
     */
    public function enrollmentHistory(): HasMany
    {
        return $this->hasMany(StudentEnrollmentHistory::class);
    }

    /**
     * Get the documents for the student.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    /**
     * Get the transport subscriptions for the student.
     */
    public function transportSubscriptions(): HasMany
    {
        return $this->hasMany(StudentTransportSubscription::class);
    }

    /**
     * Get the grade entries for the student.
     */
    // public function gradeEntries(): HasMany
    // {
    //     return $this->hasMany(GradeEntry::class);
    // }

    /**
     * Get the attendance records for the student.
     */
    // public function attendanceRecords(): HasMany
    // {
    //     return $this->hasMany(AttendanceRecord::class);
    // }

    /**
     * Get the behavioral incidents for the student.
     */
        // public function behavioralIncidents(): HasMany
        // {
        //     return $this->hasMany(BehavioralIncident::class);
        // }

    /**
     * Get the student's full name.
     */
    public function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name)
        );
    }

    /**
     * Get the student's display name (preferred or full name).
     */
    public function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->full_name
        );
    }

    /**
     * Get the student's age.
     */
    public function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date_of_birth ? Carbon::parse($this->date_of_birth)->diffInYears(now()) : null
        );
    }

    /**
     * Check if student is currently enrolled.
     */
    public function isEnrolled(): bool
    {
        return $this->enrollment_status === 'enrolled';
    }

    /**
     * Check if student has special educational needs.
     */
    public function hasSpecialNeeds(): bool
    {
        return !empty($this->accommodation_needs_json);
    }

    /**
     * Get primary emergency contact.
     */
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

    /**
     * Get current academic status summary.
     */
    public function getAcademicStatus(): array
    {
        return [
            'grade_level' => $this->current_grade_level,
            'enrollment_status' => $this->enrollment_status,
            'gpa' => $this->current_gpa,
            'attendance_rate' => $this->attendance_rate,
            'behavioral_points' => $this->behavioral_points,
        ];
    }

    /**
     * Scope to filter students by enrollment status.
     */
    public function scopeEnrolled($query)
    {
        return $query->where('enrollment_status', 'enrolled');
    }

    /**
     * Scope to filter students by grade level.
     */
    public function scopeByGradeLevel($query, string $gradeLevel)
    {
        return $query->where('current_grade_level', $gradeLevel);
    }

    /**
     * Scope to filter students by school.
     */
    public function scopeBySchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to search students by name or student number.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('student_number', 'like', "%{$search}%");
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id'; // Changed from 'student_number' to 'id' for numeric route binding
    }
}

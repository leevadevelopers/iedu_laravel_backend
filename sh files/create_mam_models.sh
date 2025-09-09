#!/bin/bash

# iEDU Academic Management - Models Generation
# Creates all Laravel models for the Academic Management module

echo "🎓 Creating iEDU Academic Management Models..."

# Create Models directory if not exists
mkdir -p app/Models/V1/Academic

#1. Teacher Model
cat > app/Models/V1/Academic/Teacher.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
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
EOF

#2. Subject Model
cat > app/Models/V1/Academic/Subject.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArray;

class Subject extends BaseModel
{
    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'subject_area',
        'grade_levels',
        'learning_standards_json',
        'prerequisites',
        'credit_hours',
        'is_core_subject',
        'is_elective',
        'status'
    ];

    protected $casts = [
        'grade_levels' => AsArray::class,
        'learning_standards_json' => 'array',
        'prerequisites' => AsArray::class,
        'credit_hours' => 'decimal:1',
        'is_core_subject' => 'boolean',
        'is_elective' => 'boolean'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('is_core_subject', true);
    }

    public function scopeElective(Builder $query): Builder
    {
        return $query->where('is_elective', true);
    }

    public function scopeByArea(Builder $query, string $area): Builder
    {
        return $query->where('subject_area', $area);
    }

    public function scopeByGradeLevel(Builder $query, string $gradeLevel): Builder
    {
        return $query->whereJsonContains('grade_levels', $gradeLevel);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCoreSubject(): bool
    {
        return $this->is_core_subject;
    }

    public function isElective(): bool
    {
        return $this->is_elective;
    }

    public function supportsGradeLevel(string $gradeLevel): bool
    {
        return in_array($gradeLevel, $this->grade_levels ?? []);
    }

    public function hasPrerequisites(): bool
    {
        return !empty($this->prerequisites);
    }
}
EOF

#3. Academic Class Model
cat > app/Models/V1/Academic/AcademicClass.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArray;

class AcademicClass extends BaseModel
{
    protected $table = 'classes';

    protected $fillable = [
        'school_id',
        'subject_id',
        'academic_year_id',
        'academic_term_id',
        'name',
        'section',
        'class_code',
        'grade_level',
        'max_students',
        'current_enrollment',
        'primary_teacher_id',
        'additional_teachers_json',
        'schedule_json',
        'room_number',
        'status'
    ];

    protected $casts = [
        'additional_teachers_json' => 'array',
        'schedule_json' => 'array',
        'max_students' => 'integer',
        'current_enrollment' => 'integer'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function primaryTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'primary_teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_class_enrollments')
                    ->withPivot('enrollment_date', 'status', 'final_grade')
                    ->withTimestamps();
    }

    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'class_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByGrade(Builder $query, string $gradeLevel): Builder
    {
        return $query->where('grade_level', $gradeLevel);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('primary_teacher_id', $teacherId);
    }

    public function scopeBySubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByAcademicYear(Builder $query, int $yearId): Builder
    {
        return $query->where('academic_year_id', $yearId);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasAvailableSeats(): bool
    {
        return $this->current_enrollment < $this->max_students;
    }

    public function getAvailableSeats(): int
    {
        return max(0, $this->max_students - $this->current_enrollment);
    }

    public function getEnrollmentPercentage(): float
    {
        if ($this->max_students == 0) return 0;
        return ($this->current_enrollment / $this->max_students) * 100;
    }

    public function addStudent($student): bool
    {
        if (!$this->hasAvailableSeats()) {
            return false;
        }

        $this->students()->attach($student->id, [
            'enrollment_date' => now(),
            'status' => 'active'
        ]);

        $this->increment('current_enrollment');
        return true;
    }

    public function removeStudent($student): bool
    {
        $this->students()->detach($student->id);
        $this->decrement('current_enrollment');
        return true;
    }
}
EOF

#4. Grading System Model
cat > app/Models/V1/Academic/GradingSystem.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArray;

class GradingSystem extends BaseModel
{
    protected $fillable = [
        'school_id',
        'name',
        'system_type',
        'applicable_grades',
        'applicable_subjects',
        'is_primary',
        'configuration_json',
        'status'
    ];

    protected $casts = [
        'applicable_grades' => AsArray::class,
        'applicable_subjects' => AsArray::class,
        'configuration_json' => 'array',
        'is_primary' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($gradingSystem) {
            if ($gradingSystem->is_primary) {
                static::where('school_id', $gradingSystem->school_id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });

        static::updating(function ($gradingSystem) {
            if ($gradingSystem->is_primary && $gradingSystem->isDirty('is_primary')) {
                static::where('school_id', $gradingSystem->school_id)
                    ->where('id', '!=', $gradingSystem->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function gradeScales(): HasMany
    {
        return $this->hasMany(GradeScale::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('system_type', $type);
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    public function supportsGrade(string $grade): bool
    {
        return in_array($grade, $this->applicable_grades ?? []);
    }

    public function supportsSubject(string $subject): bool
    {
        return in_array($subject, $this->applicable_subjects ?? []);
    }
}
EOF

#5. Grade Scale Model
cat > app/Models/V1/Academic/GradeScale.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class GradeScale extends BaseModel
{
    protected $fillable = [
        'grading_system_id',
        'school_id',
        'name',
        'scale_type',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    protected static function booted()
    {
        static::creating(function ($gradeScale) {
            if ($gradeScale->is_default) {
                static::where('grading_system_id', $gradeScale->grading_system_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
    }

    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class)->orderBy('sort_order');
    }

    // Scopes
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('scale_type', $type);
    }

    // Methods
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function getGradeForPercentage(float $percentage): ?GradeLevel
    {
        return $this->gradeLevels()
            ->where('percentage_min', '<=', $percentage)
            ->where('percentage_max', '>=', $percentage)
            ->first();
    }
}
EOF

#6. Grade Level Model
cat > app/Models/V1/Academic/GradeLevel.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GradeLevel extends BaseModel
{
    protected $fillable = [
        'grade_scale_id',
        'grade_value',
        'display_value',
        'numeric_value',
        'gpa_points',
        'percentage_min',
        'percentage_max',
        'description',
        'color_code',
        'is_passing',
        'sort_order'
    ];

    protected $casts = [
        'numeric_value' => 'decimal:2',
        'gpa_points' => 'decimal:2',
        'percentage_min' => 'decimal:2',
        'percentage_max' => 'decimal:2',
        'is_passing' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    // Scopes
    public function scopePassing(Builder $query): Builder
    {
        return $query->where('is_passing', true);
    }

    public function scopeFailing(Builder $query): Builder
    {
        return $query->where('is_passing', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    // Methods
    public function isPassing(): bool
    {
        return $this->is_passing;
    }

    public function isFailing(): bool
    {
        return !$this->is_passing;
    }

    public function isInRange(float $percentage): bool
    {
        return $percentage >= $this->percentage_min && $percentage <= $this->percentage_max;
    }
}
EOF

#7. Grade Entry Model
cat > app/Models/V1/Academic/GradeEntry.php << 'EOF'
<?php

namespace App\Models\V1\Academic;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class GradeEntry extends BaseModel
{
    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'academic_term_id',
        'assessment_name',
        'assessment_type',
        'assessment_date',
        'raw_score',
        'percentage_score',
        'letter_grade',
        'points_earned',
        'points_possible',
        'grade_category',
        'weight',
        'entered_by',
        'entered_at',
        'modified_by',
        'modified_at',
        'teacher_comments',
        'private_notes'
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'raw_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'points_earned' => 'decimal:2',
        'points_possible' => 'decimal:2',
        'weight' => 'decimal:2',
        'entered_at' => 'datetime',
        'modified_at' => 'datetime'
    ];

    protected static function booted()
    {
        static::creating(function ($gradeEntry) {
            $gradeEntry->entered_at = now();
        });

        static::updating(function ($gradeEntry) {
            $gradeEntry->modified_at = now();
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'entered_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'modified_by');
    }

    // Scopes
    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTerm(Builder $query, int $termId): Builder
    {
        return $query->where('academic_term_id', $termId);
    }

    public function scopeByAssessmentType(Builder $query, string $type): Builder
    {
        return $query->where('assessment_type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('grade_category', $category);
    }

    // Methods
    public function calculatePercentage(): float
    {
        if ($this->points_possible > 0) {
            return ($this->points_earned / $this->points_possible) * 100;
        }
        return $this->percentage_score ?? 0;
    }

    public function getWeightedScore(): float
    {
        return $this->calculatePercentage() * ($this->weight ?? 1.0);
    }

    public function isPassing(): bool
    {
        return $this->percentage_score >= 60; // Configurable threshold
    }

    public function hasComments(): bool
    {
        return !empty($this->teacher_comments);
    }

    public function wasModified(): bool
    {
        return !is_null($this->modified_at);
    }
}
EOF

echo "✅ Academic Management Models created successfully!"
echo "📁 Models created in: app/Models/V1/Academic/"
echo "📋 Created models:"
echo "   - Teacher"
echo "   - Subject"
echo "   - AcademicClass"
echo "   - GradingSystem"
echo "   - GradeScale"
echo "   - GradeLevel"
echo "   - GradeEntry"
echo "🔧 Next: Run migrations and create controllers"

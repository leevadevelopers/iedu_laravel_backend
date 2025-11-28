<?php

namespace App\Models\V1\SIS\Student;

use App\Models\V1\SIS\School\AcademicYear;
use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Student Enrollment History Model
 *
 * Tracks the enrollment history of students including grade progressions,
 * transfers, and academic status changes.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property string $enrollment_date
 * @property string|null $withdrawal_date
 * @property string $grade_level_at_enrollment
 * @property string|null $grade_level_at_withdrawal
 * @property string $enrollment_type
 * @property string|null $withdrawal_type
 * @property string|null $withdrawal_reason
 * @property string|null $previous_school
 * @property string|null $next_school
 * @property array|null $transfer_documents_json
 * @property float|null $final_gpa
 * @property float|null $credits_earned
 * @property array|null $academic_records_json
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class StudentEnrollmentHistory extends Model
{
    use HasFactory, Tenantable;

    /**
     * The table associated with the model.
     */
    protected $table = 'student_enrollment_history';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'school_id',
        'student_id',
        'academic_year_id',
        'enrollment_date',
        'withdrawal_date',
        'grade_level_at_enrollment',
        'grade_level_at_withdrawal',
        'enrollment_type',
        'withdrawal_type',
        'withdrawal_reason',
        'previous_school',
        'next_school',
        'transfer_documents_json',
        'final_gpa',
        'credits_earned',
        'academic_records_json',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'enrollment_date' => 'date',
        'withdrawal_date' => 'date',
        'transfer_documents_json' => 'array',
        'academic_records_json' => 'array',
        'final_gpa' => 'decimal:2',
        'credits_earned' => 'decimal:2',
    ];

    /**
     * Get the school for this enrollment record.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScope(\App\Models\Scopes\TenantScope::class);
    }

    /**
     * Get the student for this enrollment record.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic year for this enrollment record.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Check if enrollment is currently active.
     */
    public function isActive(): bool
    {
        return is_null($this->withdrawal_date);
    }

    /**
     * Get enrollment duration in days.
     */
    public function getEnrollmentDuration(): ?int
    {
        if (!$this->enrollment_date) {
            return null;
        }

        $endDate = $this->withdrawal_date ?: now();
        return Carbon::parse($this->enrollment_date)->diffInDays(Carbon::parse($endDate));
    }

    /**
     * Scope to filter active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('withdrawal_date');
    }

    /**
     * Scope to filter by enrollment type.
     */
    public function scopeByEnrollmentType($query, string $type)
    {
        return $query->where('enrollment_type', $type);
    }
}

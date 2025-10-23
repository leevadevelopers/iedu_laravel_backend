<?php

namespace App\Models\V1\SIS\School;

use App\Models\V1\SIS\Student\StudentEnrollmentHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Academic Year Model
 *
 * Represents time-based educational structure with terms and periods.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $code
 * @property string $start_date
 * @property string $end_date
 * @property string $term_structure
 * @property int $total_terms
 * @property int|null $total_instructional_days
 * @property string $status
 * @property bool $is_current
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AcademicYear extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'academic_years';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'school_id',
        'tenant_id',
        'name',
        'code',
        'year',
        'description',
        'start_date',
        'end_date',
        'enrollment_start_date',
        'enrollment_end_date',
        'registration_deadline',
        'term_structure',
        'total_terms',
        'total_instructional_days',
        'holidays_json',
        'status',
        'is_current',
        'created_by',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_start_date' => 'date',
        'enrollment_end_date' => 'date',
        'registration_deadline' => 'date',
        'total_terms' => 'integer',
        'total_instructional_days' => 'integer',
        'holidays_json' => 'array',
        'is_current' => 'boolean',
    ];

    /**
     * Get the school that owns the academic year.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the tenant that owns the academic year.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Settings\Tenant::class);
    }

    /**
     * Get the user who created the academic year.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the students enrolled in this academic year.
     */
    public function students(): HasMany
    {
        return $this->hasMany(\App\Models\V1\SIS\Student\Student::class, 'current_academic_year_id');
    }

    /**
     * Get the academic terms for this year.
     */
    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class);
    }

    /**
     * Get the student enrollment history for this academic year.
     */
    public function studentEnrollmentHistory(): HasMany
    {
        return $this->hasMany(StudentEnrollmentHistory::class);
    }

    /**
     * Check if the academic year is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the academic year is current.
     */
    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    /**
     * Check if the academic year is in planning phase.
     */
    public function isPlanned(): bool
    {
        return $this->status === 'planning';
    }

    /**
     * Check if the academic year is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the duration of the academic year in days.
     */
    public function getDurationInDays(): int
    {
        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date));
    }

    /**
     * Check if a given date falls within this academic year.
     */
    public function containsDate(\Carbon\Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get active terms for this academic year.
     */
    public function getActiveTerms()
    {
        return $this->terms()->where('status', 'active')->get();
    }

    /**
     * Get the next academic year.
     */
    public function getNextAcademicYear()
    {
        return self::where('school_id', $this->school_id)
                  ->where('start_date', '>', $this->end_date)
                  ->orderBy('start_date')
                  ->first();
    }

    /**
     * Get the previous academic year.
     */
    public function getPreviousAcademicYear()
    {
        return self::where('school_id', $this->school_id)
                  ->where('end_date', '<', $this->start_date)
                  ->orderBy('end_date', 'desc')
                  ->first();
    }

    /**
     * Scope to filter current academic years.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to filter active academic years.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter planned academic years.
     */
    public function scopePlanned($query)
    {
        return $query->where('status', 'planning');
    }

    /**
     * Scope to filter completed academic years.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter by school.
     */
    public function scopeBySchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to filter academic years within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($subQ) use ($startDate, $endDate) {
                  $subQ->where('start_date', '<=', $startDate)
                       ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($academicYear) {
            if (empty($academicYear->code)) {
                $academicYear->code = $academicYear->generateCode();
            }
        });
    }

    /**
     * Generate automatic code for academic year.
     */
    public function generateCode(): string
    {
        $year = $this->year ?? date('Y');
        $schoolCode = $this->school ? $this->school->code : 'SCH';

        // Extract year from year field (e.g., "2025-2026" -> "25-26")
        if (strpos($year, '-') !== false) {
            $years = explode('-', $year);
            $shortYear = substr($years[0], -2) . '-' . substr($years[1], -2);
        } else {
            $shortYear = substr($year, -2);
        }

        $prefix = 'AY'; // Academic Year prefix
        $baseCode = $prefix . $shortYear;

        // Check for duplicates and add counter if needed
        $counter = 1;
        $code = $baseCode;

        while (static::where('school_id', $this->school_id)
                    ->where('code', $code)
                    ->where('id', '!=', $this->id ?? 0)
                    ->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Get holidays as array.
     */
    public function getHolidaysAttribute()
    {
        return $this->holidays_json ?? [];
    }

    /**
     * Set holidays from array.
     */
    public function setHolidaysAttribute($value)
    {
        $this->attributes['holidays_json'] = is_array($value) ? json_encode($value) : $value;
    }
}

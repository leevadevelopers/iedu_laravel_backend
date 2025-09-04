<?php

namespace App\Models\V1\SIS\School;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;

/**
 * Academic Term Model
 *
 * Represents terms/periods within an academic year.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $school_id
 * @property int $tenant_id
 * @property string $name
 * @property string $type
 * @property int|null $term_number
 * @property string|null $description
 * @property string $start_date
 * @property string $end_date
 * @property int|null $instructional_days
 * @property string|null $enrollment_start_date
 * @property string|null $enrollment_end_date
 * @property string|null $registration_deadline
 * @property string|null $grades_due_date
 * @property array|null $holidays_json
 * @property string $status
 * @property bool $is_current
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class AcademicTerm extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'academic_terms';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'academic_year_id',
        'school_id',
        'tenant_id',
        'name',
        'type',
        'term_number',
        'description',
        'start_date',
        'end_date',
        'instructional_days',
        'enrollment_start_date',
        'enrollment_end_date',
        'registration_deadline',
        'grades_due_date',
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
        'grades_due_date' => 'date',
        'term_number' => 'integer',
        'instructional_days' => 'integer',
        'is_current' => 'boolean',
        'holidays_json' => 'array',
    ];

    /**
     * Get the academic year that owns the term.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the school that owns the term.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the user who created the term.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the students enrolled in this term.
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'current_term_id');
    }

    /**
     * Check if the term is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the term is planned.
     */
    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    /**
     * Check if the term is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if a given date falls within this term.
     */
    public function containsDate(\Carbon\Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the duration of the term in days.
     */
    public function getDurationInDays(): int
    {
        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date));
    }

    /**
     * Get the next term in the academic year.
     */
    public function getNextTerm()
    {
        return self::where('academic_year_id', $this->academic_year_id)
                  ->where('term_number', '>', $this->term_number)
                  ->orderBy('term_number')
                  ->first();
    }

    /**
     * Get the previous term in the academic year.
     */
    public function getPreviousTerm()
    {
        return self::where('academic_year_id', $this->academic_year_id)
                  ->where('term_number', '<', $this->term_number)
                  ->orderBy('term_number', 'desc')
                  ->first();
    }

    /**
     * Check if this is the first term in the academic year.
     */
    public function isFirstTerm(): bool
    {
        return $this->term_number === 1;
    }

    /**
     * Check if this is the last term in the academic year.
     */
    public function isLastTerm(): bool
    {
        $maxTermNumber = self::where('academic_year_id', $this->academic_year_id)
                            ->max('term_number');
        return $this->term_number === $maxTermNumber;
    }

    /**
     * Scope to filter active terms.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter planned terms.
     */
    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    /**
     * Scope to filter completed terms.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter by academic year.
     */
    public function scopeByAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope to filter by school.
     */
    public function scopeBySchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to filter terms within a date range.
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
}

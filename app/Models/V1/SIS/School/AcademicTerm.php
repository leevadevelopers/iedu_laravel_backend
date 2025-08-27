<?php

namespace App\Models\V1\SIS\School;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Academic Term Model
 *
 * Represents terms/periods within an academic year.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $school_id
 * @property string $name
 * @property int $term_number
 * @property string $start_date
 * @property string $end_date
 * @property int $instructional_days
 * @property string $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AcademicTerm extends Model
{
    use HasFactory;

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
        'name',
        'term_number',
        'start_date',
        'end_date',
        'instructional_days',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'term_number' => 'integer',
        'instructional_days' => 'integer',
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

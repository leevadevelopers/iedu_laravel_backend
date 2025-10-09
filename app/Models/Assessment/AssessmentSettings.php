<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\AcademicTerm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSettings extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'academic_term_id',
        'assessments_count',
        'default_passing_score',
        'rounding_policy',
        'decimal_places',
        'allow_grade_review',
        'review_deadline_days',
        'config',
    ];

    protected $casts = [
        'default_passing_score' => 'decimal:2',
        'allow_grade_review' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Get the academic term.
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }
}


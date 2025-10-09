<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'name',
        'description',
        'weight_pct',
        'max_marks',
        'rubric',
        'order',
    ];

    protected $casts = [
        'weight_pct' => 'decimal:2',
        'max_marks' => 'decimal:2',
        'rubric' => 'array',
    ];

    /**
     * Get the assessment that owns this component.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    /**
     * Get the grade entries for this component.
     */
    public function gradeEntries(): HasMany
    {
        return $this->hasMany(GradeEntry::class, 'component_id');
    }
}


<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentTerm extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'academic_term_id',
        'start_date',
        'end_date',
        'is_published',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_published' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the academic term that owns this assessment term.
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(\App\Models\V1\AcademicTerm::class, 'academic_term_id');
    }

    /**
     * Get the user who created this term.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the assessments for this term.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'term_id');
    }

    /**
     * Get the gradebooks for this term.
     */
    public function gradebooks(): HasMany
    {
        return $this->hasMany(Gradebook::class, 'term_id');
    }

    /**
     * Get the settings for this term.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(AssessmentSettings::class, 'academic_term_id');
    }

    /**
     * Scope to get only published terms.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to get only active terms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


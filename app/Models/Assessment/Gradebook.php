<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Subject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gradebook extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'class_id',
        'term_id',
        'title',
        'description',
        'file_path',
        'status',
        'uploaded_by',
        'uploaded_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the subject.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    /**
     * Get the class.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    /**
     * Get the assessment term.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AssessmentTerm::class, 'term_id');
    }

    /**
     * Get the user who uploaded the gradebook.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who approved the gradebook.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the files associated with this gradebook.
     */
    public function files(): HasMany
    {
        return $this->hasMany(GradebookFile::class, 'gradebook_id');
    }

    /**
     * Scope to get approved gradebooks.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get pending gradebooks.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}


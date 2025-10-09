<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GradeReview extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'grade_entry_id',
        'requester_id',
        'reason',
        'details',
        'status',
        'reviewer_id',
        'reviewer_comments',
        'original_marks',
        'revised_marks',
        'submitted_at',
        'reviewed_at',
        'resolved_at',
    ];

    protected $casts = [
        'original_marks' => 'decimal:2',
        'revised_marks' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the grade entry.
     */
    public function gradeEntry(): BelongsTo
    {
        return $this->belongsTo(GradeEntry::class, 'grade_entry_id');
    }

    /**
     * Get the requester (student or parent).
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Scope to get pending reviews.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get resolved reviews.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope to get reviews by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}


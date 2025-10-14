<?php

namespace App\Models\Assessment;

use App\Models\User;
use App\Models\V1\Academic\GradeEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradesAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_entry_id',
        'changed_by',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'reason',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the grade entry.
     */
    public function gradeEntry(): BelongsTo
    {
        return $this->belongsTo(GradeEntry::class, 'grade_entry_id');
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}


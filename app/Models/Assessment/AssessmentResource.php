<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'type',
        'title',
        'description',
        'url_or_path',
        'mime_type',
        'size',
        'access_policy',
        'order',
    ];

    /**
     * Get the assessment that owns this resource.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}


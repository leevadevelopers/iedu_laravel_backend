<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradebookFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'gradebook_id',
        'filename',
        'path',
        'mime_type',
        'size',
        'disk',
    ];

    /**
     * Get the gradebook that owns this file.
     */
    public function gradebook(): BelongsTo
    {
        return $this->belongsTo(Gradebook::class, 'gradebook_id');
    }
}


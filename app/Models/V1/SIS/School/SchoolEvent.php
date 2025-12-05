<?php

namespace App\Models\V1\SIS\School;

use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEvent extends Model
{
    use HasFactory;

    protected $table = 'school_events';

    protected $fillable = [
        'school_id',
        'title',
        'description',
        'event_type',
        'start_date',
        'end_date',
        'all_day',
        'location',
        'color',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'all_day' => 'boolean',
    ];

    /**
     * Get the school that owns the event
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}


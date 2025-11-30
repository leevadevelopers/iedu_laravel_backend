<?php

namespace App\Models\Communication;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'title',
        'content',
        'recipients',
        'channels',
        'status',
        'scheduled_at',
        'published_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'recipients' => 'array',
        'channels' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null;
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduled_at !== null;
    }

    public function shouldPublishNow(): bool
    {
        return $this->isScheduled() && $this->scheduled_at <= now();
    }
}


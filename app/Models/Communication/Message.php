<?php

namespace App\Models\Communication;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use App\Models\V1\Academic\AcademicClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'sender_id',
        'subject',
        'message',
        'thread_id',
        'class_id',
        'recipient_ids',
        'student_ids',
        'channels',
        'is_read',
        'read_at',
        'metadata',
    ];

    protected $casts = [
        'recipient_ids' => 'array',
        'student_ids' => 'array',
        'channels' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'thread_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}


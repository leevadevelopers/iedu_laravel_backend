<?php

namespace App\Models\Communication;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SMSLog extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'recipient_phone',
        'message',
        'template_id',
        'status',
        'provider',
        'provider_message_id',
        'provider_response',
        'cost',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'sent_by',
        'metadata',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}


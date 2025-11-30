<?php

namespace App\Models\V1\Financial;

use App\Models\User;
use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Payment extends Model
{
    use HasFactory, Tenantable, HasSchoolScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'invoice_id',
        'reference',
        'amount',
        'method',
        'status',
        'paid_at',
        'transaction_id',
        'notes',
        'metadata',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->reference) {
                $model->reference = 'PAY-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
            }
            if (auth()->check() && !$model->processed_by) {
                $model->processed_by = auth()->id();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'amount', 'status', 'paid_at'])
            ->logOnlyDirty();
    }
}

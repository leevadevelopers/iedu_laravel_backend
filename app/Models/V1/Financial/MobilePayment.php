<?php

namespace App\Models\V1\Financial;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePayment extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'student_id',
        'invoice_id',
        'payment_id',
        'reference_code',
        'provider',
        'amount',
        'phone',
        'status',
        'transaction_id',
        'instructions',
        'provider_response',
        'initiated_at',
        'completed_at',
        'expires_at',
        'payment_id_fk',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->payment_id) {
                $model->payment_id = 'MP-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id_fk');
    }
}


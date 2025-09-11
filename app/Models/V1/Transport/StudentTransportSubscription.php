<?php

namespace App\Models\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditingTrait;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class StudentTransportSubscription extends Model implements Auditable
{
    use HasFactory, MultiTenant, SoftDeletes, AuditingTrait;

    protected $fillable = [
        'school_id',
        'student_id',
        'pickup_stop_id',
        'dropoff_stop_id',
        'transport_route_id',
        'qr_code',
        'rfid_card_id',
        'subscription_type',
        'start_date',
        'end_date',
        'monthly_fee',
        'auto_renewal',
        'authorized_parents',
        'status',
        'special_needs',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'monthly_fee' => 'decimal:2',
        'auto_renewal' => 'boolean',
        'authorized_parents' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($subscription) {
            if (empty($subscription->qr_code)) {
                $subscription->qr_code = $subscription->generateQrCode();
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'pickup_stop_id');
    }

    public function dropoffStop(): BelongsTo
    {
        return $this->belongsTo(BusStop::class, 'dropoff_stop_id');
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function transportEvents(): HasMany
    {
        return $this->hasMany(StudentTransportEvent::class, 'student_id', 'student_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->whereDate('start_date', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('end_date')
                          ->orWhereDate('end_date', '>=', now());
                    });
    }

    public function scopeExpiring($query, $days = 30)
    {
        return $query->whereNotNull('end_date')
                    ->whereDate('end_date', '<=', now()->addDays($days));
    }

    // Methods
    protected function generateQrCode(): string
    {
        return 'STU' . $this->school_id . $this->student_id . time();
    }

    public function generateQrCodeImage(): string
    {
        $qrCode = new QrCode($this->qr_code);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return base64_encode($result->getString());
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               $this->start_date <= now() &&
               ($this->end_date === null || $this->end_date >= now());
    }

    public function isExpiring($days = 30): bool
    {
        return $this->end_date &&
               $this->end_date <= now()->addDays($days);
    }

    public function getRemainingDays(): ?int
    {
        return $this->end_date ? now()->diffInDays($this->end_date) : null;
    }

    public function canAutoRenew(): bool
    {
        return $this->auto_renewal && $this->isExpiring(7);
    }
}

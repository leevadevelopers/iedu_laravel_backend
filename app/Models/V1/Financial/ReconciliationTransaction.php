<?php

namespace App\Models\V1\Financial;

use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_import_id',
        'transaction_id',
        'amount',
        'phone',
        'transaction_date',
        'description',
        'match_status',
        'matched_student_id',
        'matched_payment_id',
        'confidence',
        'match_details',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'match_details' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ReconciliationImport::class);
    }

    public function matchedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'matched_student_id');
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(MobilePayment::class, 'matched_payment_id');
    }
}


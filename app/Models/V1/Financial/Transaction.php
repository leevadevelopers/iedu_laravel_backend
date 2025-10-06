<?php

namespace App\Models\V1\Financial;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'account_id',
        'transactable_id',
        'transactable_type',
        'type',
        'amount',
        'description',
        'transaction_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function transactable(): MorphTo
    {
        return $this->morphTo();
    }
}

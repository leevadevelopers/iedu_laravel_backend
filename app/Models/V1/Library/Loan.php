<?php

namespace App\Models\V1\Library;

use App\Models\User;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Loan extends Model
{
    use HasFactory, HasTenantScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'book_copy_id',
        'borrower_id',
        'loaned_at',
        'due_at',
        'returned_at',
        'status',
        'fine_amount',
        'created_by',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'loaned_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'fine_amount' => 'decimal:2',
    ];

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function incident(): HasOne
    {
        return $this->hasOne(Incident::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at->isPast() && !$this->returned_at;
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return now()->diffInDays($this->due_at);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'returned_at', 'fine_amount'])
            ->logOnlyDirty();
    }
}

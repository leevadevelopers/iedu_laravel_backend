<?php

namespace App\Models\V1\Library;

use App\Models\User;
use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Incident extends Model
{
    use HasFactory, HasTenantScope, LogsActivity;

    protected $table = 'library_incidents';

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'book_copy_id',
        'reporter_id',
        'type',
        'description',
        'status',
        'assessed_fine',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'assessed_fine' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function bookCopy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assessed_fine', 'resolved_at'])
            ->logOnlyDirty();
    }
}

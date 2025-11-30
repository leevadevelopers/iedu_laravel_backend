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

class Expense extends Model
{
    use HasFactory, Tenantable, HasSchoolScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'account_id',
        'category',
        'amount',
        'description',
        'incurred_at',
        'receipt_path',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'incurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category', 'amount', 'incurred_at'])
            ->logOnlyDirty();
    }
}

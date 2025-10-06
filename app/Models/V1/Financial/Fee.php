<?php

namespace App\Models\V1\Financial;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Fee extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'amount',
        'recurring',
        'frequency',
        'applied_to',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recurring' => 'boolean',
        'applied_to' => 'array',
        'is_active' => 'boolean',
    ];

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'amount', 'is_active'])
            ->logOnlyDirty();
    }
}

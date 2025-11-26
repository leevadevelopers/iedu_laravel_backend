<?php

namespace App\Models\V1\Financial;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FinancialAccount extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'code',
        'account_number',
        'type',
        'bank_name',
        'bank_branch',
        'currency',
        'balance',
        'initial_balance',
        'is_active',
        'description',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];
    
    // Accessors para compatibilidade
    public function getAccountTypeAttribute()
    {
        return $this->type;
    }
    
    public function getStatusAttribute()
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'account_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'balance'])
            ->logOnlyDirty();
    }
}

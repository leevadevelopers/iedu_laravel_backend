<?php

namespace App\Models\V1\Financial;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FinancialAccount extends Model
{
    use HasFactory, SoftDeletes, Tenantable, HasSchoolScope, LogsActivity;

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

    protected static function booted(): void
    {
        static::creating(function ($model) {
            // Gerar código automaticamente se não fornecido
            if (!$model->code) {
                $model->code = static::generateUniqueCode();
            }
        });
    }

    /**
     * Gera um código único para a conta financeira
     */
    protected static function generateUniqueCode(): string
    {
        $prefix = 'ACC';
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $code = $prefix . '-' . strtoupper(uniqid());
            $exists = static::where('code', $code)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            // Fallback: usar timestamp se não conseguir gerar código único
            $code = $prefix . '-' . now()->format('YmdHis') . '-' . rand(1000, 9999);
        }

        return $code;
    }

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

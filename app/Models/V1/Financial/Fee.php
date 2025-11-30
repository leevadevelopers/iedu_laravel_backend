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

class Fee extends Model
{
    use HasFactory, SoftDeletes, Tenantable, HasSchoolScope, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'school_id',
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
     * Gera um código único para a taxa
     */
    protected static function generateUniqueCode(): string
    {
        $prefix = 'FEE';
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

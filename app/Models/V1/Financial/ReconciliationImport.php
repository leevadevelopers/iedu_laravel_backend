<?php

namespace App\Models\V1\Financial;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReconciliationImport extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'import_id',
        'provider',
        'period_start',
        'period_end',
        'file_path',
        'status',
        'total_transactions',
        'matched',
        'unmatched',
        'pending',
        'imported_by',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->import_id) {
                $model->import_id = (string) Str::uuid();
            }
        });
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ReconciliationTransaction::class);
    }
}


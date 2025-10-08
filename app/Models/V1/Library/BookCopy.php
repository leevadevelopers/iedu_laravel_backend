<?php

namespace App\Models\V1\Library;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class BookCopy extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'book_id',
        'barcode',
        'location',
        'status',
        'notes',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->whereIn('status', ['active', 'overdue']);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['barcode', 'status', 'location'])
            ->logOnlyDirty();
    }
}

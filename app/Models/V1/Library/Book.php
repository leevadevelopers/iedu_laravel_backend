<?php

namespace App\Models\V1\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Book extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'tenant_id',
        'collection_id',
        'publisher_id',
        'title',
        'isbn',
        'language',
        'summary',
        'visibility',
        'restricted_tenants',
        'subjects',
        'published_at',
        'edition',
        'pages',
        'cover_image',
        'created_by',
    ];

    protected $casts = [
        'restricted_tenants' => 'array',
        'subjects' => 'array',
        'published_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('visibility', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $tenantId = auth()->user()->tenant_id;

                $builder->where(function ($query) use ($tenantId) {
                    $query->where('visibility', 'public')
                          ->orWhere(function ($q) use ($tenantId) {
                              $q->where('visibility', 'tenant')
                                ->where('tenant_id', $tenantId);
                          })
                          ->orWhere(function ($q) use ($tenantId) {
                              $q->where('visibility', 'restricted')
                                ->whereJsonContains('restricted_tenants', $tenantId);
                          });
                });
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && !$model->tenant_id && $model->visibility === 'tenant') {
                $model->tenant_id = auth()->user()->tenant_id;
            }
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_author')
            ->withPivot('order')
            ->orderByPivot('order');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BookFile::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function availableCopies(): HasMany
    {
        return $this->copies()->where('status', 'available');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'isbn', 'visibility'])
            ->logOnlyDirty();
    }
}

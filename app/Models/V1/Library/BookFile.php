<?php

namespace App\Models\V1\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BookFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'tenant_id',
        'type',
        'file_path',
        'external_url',
        'size',
        'mime',
        'access_policy',
        'allowed_roles',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        // Global scope: allow public files from any tenant, but tenant-only files only from current tenant
        static::addGlobalScope('access_policy', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $tenantId = auth()->user()->tenant_id;

                $builder->where(function ($query) use ($tenantId) {
                    $query->where('access_policy', 'public')
                          ->orWhere(function ($q) use ($tenantId) {
                              $q->where('access_policy', 'tenant_only')
                                ->where('tenant_id', $tenantId);
                          })
                          ->orWhere(function ($q) use ($tenantId) {
                              // For specific_roles, check if user has the role and file belongs to their tenant
                              $q->where('access_policy', 'specific_roles')
                                ->where('tenant_id', $tenantId);
                          });
                });
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && !$model->tenant_id) {
                // Get tenant_id from the book if available
                if ($model->book_id) {
                    $book = Book::find($model->book_id);
                    if ($book && $book->tenant_id) {
                        $model->tenant_id = $book->tenant_id;
                    } else {
                        $model->tenant_id = auth()->user()->tenant_id;
                    }
                } else {
                    $model->tenant_id = auth()->user()->tenant_id;
                }
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)
            ->withoutGlobalScope('visibility');
    }

    public function getUrl(): ?string
    {
        if ($this->external_url) {
            return $this->external_url;
        }

        if ($this->file_path) {
            return Storage::temporaryUrl(
                $this->file_path,
                now()->addMinutes(30)
            );
        }

        return null;
    }

    public function canAccess(User $user): bool
    {
        // Public files are accessible by anyone
        if ($this->access_policy === 'public') {
            return true;
        }

        // Get user tenant_id from session or user model
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        // Tenant-only files are only accessible by the same tenant
        if ($this->access_policy === 'tenant_only') {
            return $this->tenant_id === $userTenantId;
        }

        // Specific roles: user must have the role AND be from the same tenant
        if ($this->access_policy === 'specific_roles' && $this->allowed_roles) {
            if ($this->tenant_id !== $userTenantId) {
                return false;
            }
            return $user->hasAnyRole($this->allowed_roles);
        }

        return false;
    }
}

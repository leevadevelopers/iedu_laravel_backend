<?php

namespace App\Models\V1\Library;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BookFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
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
        if ($this->access_policy === 'public') {
            return true;
        }

        if ($this->access_policy === 'tenant_only') {
            return $user->tenant_id === $this->book->tenant_id;
        }

        if ($this->access_policy === 'specific_roles' && $this->allowed_roles) {
            return $user->hasAnyRole($this->allowed_roles);
        }

        return false;
    }
}

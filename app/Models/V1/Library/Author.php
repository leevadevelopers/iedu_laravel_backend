<?php

namespace App\Models\V1\Library;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Author extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'name',
        'bio',
        'country',
    ];

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_author')
            ->withPivot('order')
            ->orderByPivot('order');
    }
}

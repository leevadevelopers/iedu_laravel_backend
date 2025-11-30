<?php

namespace App\Models\V1\Library;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'tenant_id',
        'name',
        'country',
        'website',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}

<?php

namespace App\Models\Assessment;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentType extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'default_weight',
        'color',
        'is_active',
    ];

    protected $casts = [
        'default_weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the assessments for this type.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'type_id');
    }

    /**
     * Scope to get only active types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


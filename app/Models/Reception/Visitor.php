<?php

namespace App\Models\Reception;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visitor extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'type',
        'student_id',
        'purpose',
        'attended_by',
        'resolved',
        'notes',
        'arrived_at',
        'departed_at',
        'metadata',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'arrived_at' => 'datetime',
        'departed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->arrived_at) {
                $model->arrived_at = now();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function markAsResolved(): void
    {
        $this->update([
            'resolved' => true,
            'departed_at' => now(),
        ]);
    }
}


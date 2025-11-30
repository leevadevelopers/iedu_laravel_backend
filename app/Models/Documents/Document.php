<?php

namespace App\Models\Documents;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'template',
        'student_id',
        'purpose',
        'signed_by',
        'notes',
        'document_id',
        'download_url',
        'pdf_url',
        'status',
        'generated_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->document_id) {
                $model->document_id = 'DOC-' . now()->format('Ymd') . '-' . strtoupper(uniqid());
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}


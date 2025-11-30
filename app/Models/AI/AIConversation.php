<?php

namespace App\Models\AI;

use App\Models\Traits\Tenantable;
use App\Models\Traits\HasSchoolScope;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AIConversation extends Model
{
    use HasFactory, Tenantable, HasSchoolScope;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'student_id',
        'conversation_id',
        'subject',
        'question',
        'answer',
        'explanation',
        'examples',
        'practice_problems',
        'audio_url',
        'image_url',
        'context',
        'tokens_used',
        'cost',
        'status',
        'metadata',
    ];

    protected $casts = [
        'examples' => 'array',
        'practice_problems' => 'array',
        'context' => 'array',
        'tokens_used' => 'integer',
        'cost' => 'decimal:6',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (!$model->conversation_id) {
                $model->conversation_id = (string) Str::uuid();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}


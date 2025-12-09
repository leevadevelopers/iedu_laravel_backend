<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\Traits\Tenantable;
use App\Models\User;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\School\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonPlan extends BaseModel
{
    use Tenantable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'school_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'week_start',
        'title',
        'status',
        'visibility',
        'day_blocks',
        'objectives',
        'materials',
        'activities',
        'assessment_links',
        'tags',
        'share_with_classes',
        'homework',
        'notes',
        'lesson_id',
        'copied_from_plan_id',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'week_start' => 'date',
        'published_at' => 'datetime',
        'day_blocks' => 'array',
        'objectives' => 'array',
        'materials' => 'array',
        'activities' => 'array',
        'assessment_links' => 'array',
        'tags' => 'array',
        'share_with_classes' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}


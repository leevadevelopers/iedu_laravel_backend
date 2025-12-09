<?php

namespace App\Http\Resources\V1\Schedule;

use App\Http\Resources\V1\Academic\SubjectLiteResource;
use App\Http\Resources\V1\Academic\ClassLiteResource;
use App\Http\Resources\V1\Academic\TeacherLiteResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'school_id' => $this->school_id,
            'class_id' => $this->class_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'week_start' => optional($this->week_start)->toDateString(),
            'title' => $this->title,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'day_blocks' => $this->day_blocks,
            'objectives' => $this->objectives,
            'materials' => $this->materials,
            'activities' => $this->activities,
            'assessment_links' => $this->assessment_links,
            'tags' => $this->tags,
            'share_with_classes' => $this->share_with_classes,
            'homework' => $this->homework,
            'notes' => $this->notes,
            'lesson_id' => $this->lesson_id,
            'copied_from_plan_id' => $this->copied_from_plan_id,
            'published_at' => optional($this->published_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'subject' => new SubjectLiteResource($this->whenLoaded('subject')),
            'class' => new ClassLiteResource($this->whenLoaded('class')),
            'teacher' => new TeacherLiteResource($this->whenLoaded('teacher')),
        ];
    }
}


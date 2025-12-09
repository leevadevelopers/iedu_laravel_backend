<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\LessonPlan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LessonPlanService extends BaseScheduleService
{
    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = LessonPlan::query()
            ->where('tenant_id', $this->getCurrentTenantId())
            ->where('school_id', $this->getCurrentSchoolId())
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }

        if (!empty($filters['week_start'])) {
            $query->whereDate('week_start', $filters['week_start']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('notes', 'like', '%' . $filters['search'] . '%');
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderByDesc('week_start')->paginate($perPage);
    }

    public function getWeekView(string $weekStart, ?int $classId = null): Collection
    {
        $query = LessonPlan::query()
            ->where('tenant_id', $this->getCurrentTenantId())
            ->where('school_id', $this->getCurrentSchoolId())
            ->whereDate('week_start', $weekStart)
            ->with(['subject', 'class', 'teacher']);

        if ($classId) {
            $query->where('class_id', $classId);
        }

        return $query->get();
    }

    public function getCalendar(array $filters = []): Collection
    {
        $query = LessonPlan::query()
            ->where('tenant_id', $this->getCurrentTenantId())
            ->where('school_id', $this->getCurrentSchoolId())
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('week_start', [$filters['start_date'], $filters['end_date']]);
        }

        return $query->get();
    }

    public function getLibrary(array $filters = []): LengthAwarePaginator
    {
        $query = LessonPlan::query()
            ->where('visibility', '!=', 'private')
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $query->whereJsonContains('tags', $tags);
        }

        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderByDesc('published_at')->paginate($perPage);
    }

    public function createDraft(array $data): LessonPlan
    {
        $data['tenant_id'] = $this->getCurrentTenantId();
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['status'] = 'draft';
        $data['visibility'] = $data['visibility'] ?? 'private';
        $data['created_by'] = Auth::id();

        return LessonPlan::create($data);
    }

    public function updatePlan(LessonPlan $plan, array $data): LessonPlan
    {
        $this->validateSchoolOwnership($plan);

        $plan->fill($data);
        $plan->updated_by = Auth::id();
        $plan->save();

        return $plan->fresh(['subject', 'class', 'teacher']);
    }

    public function publishPlan(LessonPlan $plan, array $data = []): LessonPlan
    {
        $this->validateSchoolOwnership($plan);

        $plan->fill($data);
        $plan->status = 'published';
        $plan->published_at = Carbon::now();
        $plan->updated_by = Auth::id();
        $plan->save();

        return $plan->fresh(['subject', 'class', 'teacher']);
    }

    public function duplicatePlan(LessonPlan $plan): LessonPlan
    {
        $this->validateSchoolOwnership($plan);

        $copy = $plan->replicate();
        $copy->status = 'draft';
        $copy->visibility = 'private';
        $copy->published_at = null;
        $copy->copied_from_plan_id = $plan->id;
        $copy->created_by = Auth::id();
        $copy->updated_by = null;
        $copy->save();

        return $copy->fresh(['subject', 'class', 'teacher']);
    }

    public function deletePlan(LessonPlan $plan): bool
    {
        $this->validateSchoolOwnership($plan);

        if (!in_array($plan->status, ['draft', 'archived'])) {
            throw new \Exception('Only draft or archived plans can be deleted');
        }

        return (bool) $plan->delete();
    }

    public function share(LessonPlan $plan, string $visibility, ?array $shareWithClasses = null): LessonPlan
    {
        $this->validateSchoolOwnership($plan);

        $plan->visibility = $visibility;
        if (!empty($shareWithClasses)) {
            $plan->share_with_classes = $shareWithClasses;
        }
        $plan->updated_by = Auth::id();
        $plan->save();

        return $plan->fresh(['subject', 'class', 'teacher']);
    }

    public function attachLesson(LessonPlan $plan, int $lessonId): LessonPlan
    {
        $this->validateSchoolOwnership($plan);

        $plan->lesson_id = $lessonId;
        $plan->updated_by = Auth::id();
        $plan->save();

        return $plan->fresh(['lesson', 'subject', 'class', 'teacher']);
    }
}


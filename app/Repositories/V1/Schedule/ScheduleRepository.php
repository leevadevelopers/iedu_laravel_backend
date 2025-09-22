<?php

namespace App\Repositories\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository extends BaseScheduleRepository
{
    protected function getModelClass(): string
    {
        return Schedule::class;
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('classroom', 'like', "%{$search}%")
              ->orWhereHas('subject', function ($sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('teacher', function ($tq) use ($search) {
                  $tq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%");
              })
              ->orWhereHas('class', function ($cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%");
              });
        });
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (isset($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        return $query;
    }

    public function getByTeacher(int $teacherId): Collection
    {
        return $this->schoolScoped()
            ->where('teacher_id', $teacherId)
            ->where('status', 'active')
            ->with(['subject', 'class', 'academicYear'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getByClass(int $classId): Collection
    {
        return $this->schoolScoped()
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->with(['subject', 'teacher', 'academicYear'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getWeeklySchedule(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        return $query->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get();
    }

    public function findConflicts(int $teacherId, string $dayOfWeek, string $startTime, string $endTime, ?int $excludeId = null): Collection
    {
        $query = $this->schoolScoped()
            ->conflictsWith($teacherId, $dayOfWeek, $startTime, $endTime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'class'])->get();
    }

    public function getByTimeSlot(string $dayOfWeek, string $startTime, string $endTime): Collection
    {
        return $this->schoolScoped()
            ->byDay($dayOfWeek)
            ->byTimeRange($startTime, $endTime)
            ->where('status', 'active')
            ->with(['teacher', 'subject', 'class'])
            ->get();
    }

    public function getClassroomSchedule(string $classroom): Collection
    {
        return $this->schoolScoped()
            ->where('classroom', $classroom)
            ->where('status', 'active')
            ->with(['teacher', 'subject', 'class'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getDashboardStats(): array
    {
        $query = $this->schoolScoped();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'suspended' => $query->where('status', 'suspended')->count(),
            'by_period' => $query->where('status', 'active')
                ->groupBy('period')
                ->selectRaw('period, count(*) as count')
                ->pluck('count', 'period')
                ->toArray(),
            'online_classes' => $query->where('is_online', true)->count(),
            'unique_teachers' => $query->distinct('teacher_id')->count(),
            'unique_classrooms' => $query->whereNotNull('classroom')->distinct('classroom')->count()
        ];
    }
}

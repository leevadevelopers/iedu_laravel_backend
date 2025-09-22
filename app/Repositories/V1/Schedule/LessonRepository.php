<?php

namespace App\Repositories\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class LessonRepository extends BaseScheduleRepository
{
    protected function getModelClass(): string
    {
        return Lesson::class;
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('content_summary', 'like', "%{$search}%")
              ->orWhereHas('subject', function ($sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('teacher', function ($tq) use ($search) {
                  $tq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%");
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

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        return $query;
    }

    public function getByTeacher(int $teacherId, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('teacher_id', $teacherId)
            ->with(['subject', 'class', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('lesson_date', 'desc')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getByClass(int $classId, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('class_id', $classId)
            ->with(['subject', 'teacher', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('lesson_date', 'desc')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getUpcoming(int $limit = 10, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->upcoming()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->limit($limit)->get();
    }

    public function getToday(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->today()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('start_time')->get();
    }

    public function getThisWeek(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->thisWeek()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getByDateRange(string $startDate, string $endDate, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->byDateRange($startDate, $endDate)
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getDashboardStats(): array
    {
        $query = $this->schoolScoped();
        $today = now()->toDateString();

        return [
            'total' => $query->count(),
            'today' => $query->where('lesson_date', $today)->count(),
            'this_week' => $query->whereBetween('lesson_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString()
            ])->count(),
            'completed_today' => $query->where('lesson_date', $today)
                ->where('status', 'completed')->count(),
            'upcoming_today' => $query->where('lesson_date', $today)
                ->where('status', 'scheduled')->count(),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
                ->toArray(),
            'online_lessons' => $query->where('is_online', true)->count(),
            'average_attendance' => $query->whereNotNull('attendance_rate')
                ->avg('attendance_rate') ?? 0
        ];
    }

    public function getAttendanceStats(): array
    {
        $lessons = $this->schoolScoped()
            ->where('status', 'completed')
            ->whereNotNull('attendance_rate')
            ->get(['attendance_rate', 'expected_students', 'present_students']);

        if ($lessons->isEmpty()) {
            return [
                'average_attendance_rate' => 0,
                'total_expected' => 0,
                'total_present' => 0,
                'lessons_count' => 0
            ];
        }

        return [
            'average_attendance_rate' => round($lessons->avg('attendance_rate'), 2),
            'total_expected' => $lessons->sum('expected_students'),
            'total_present' => $lessons->sum('present_students'),
            'lessons_count' => $lessons->count()
        ];
    }
}

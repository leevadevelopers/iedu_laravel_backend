<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\V1\Schedule\ScheduleConflict;
use App\Models\V1\Schedule\Lesson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ScheduleService extends BaseScheduleService
{
    public function __construct()
    {
        // No repository dependency needed
    }

    public function createSchedule(array $data)
    {
        // Validate data
        $this->validateScheduleData($data);

        // Check for conflicts
        $conflicts = $this->checkConflicts($data);
        if (!empty($conflicts)) {
            throw new \Exception('Schedule conflicts detected: ' . implode(', ', $conflicts));
        }

        // Create schedule
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['created_by'] = auth('api')->id();

        DB::beginTransaction();
        try {
            $schedule = Schedule::create($data);

            // Generate lessons if requested
            if ($data['auto_generate_lessons'] ?? false) {
                $this->generateLessonsForSchedule($schedule);
            }

            DB::commit();
            return $schedule;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function updateSchedule(Schedule $schedule, array $data)
    {
        $this->validateSchoolOwnership($schedule);

        // Check for conflicts (excluding current schedule)
        if (isset($data['teacher_id']) || isset($data['day_of_week']) ||
            isset($data['start_time']) || isset($data['end_time'])) {
            $conflicts = $this->checkConflicts($data, $schedule->id);
            if (!empty($conflicts)) {
                throw new \Exception('Schedule conflicts detected: ' . implode(', ', $conflicts));
            }
        }

        $data['updated_by'] = auth('api')->id();
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function deleteSchedule(Schedule $schedule): bool
    {
        $this->validateSchoolOwnership($schedule);

        // Check if schedule has future lessons
        $futureLessons = $schedule->lessons()
            ->where('lesson_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->exists();

        if ($futureLessons) {
            throw new \Exception('Cannot delete schedule with future scheduled lessons');
        }

        return $schedule->delete();
    }

    public function getTeacherSchedule(int $teacherId): Collection
    {
        return Schedule::where('school_id', $this->getCurrentSchoolId())
            ->byTeacher($teacherId)
            ->active()
            ->with(['subject', 'class', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getClassSchedule(int $classId): Collection
    {
        return Schedule::where('school_id', $this->getCurrentSchoolId())
            ->byClass($classId)
            ->active()
            ->with(['subject', 'class', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getWeeklySchedule(array $filters = []): Collection
    {
        $query = Schedule::where('school_id', $this->getCurrentSchoolId())
            ->active()
            ->with(['subject', 'class', 'teacher']);

        // Apply filters
        if (!empty($filters['teacher_id'])) {
            $query->byTeacher($filters['teacher_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['day_of_week'])) {
            $query->byDay($filters['day_of_week']);
        }

        if (!empty($filters['period'])) {
            $query->byPeriod($filters['period']);
        }

        return $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function checkConflicts(array $scheduleData, ?int $excludeId = null): array
    {
        $conflicts = [];

        $teacherId = $scheduleData['teacher_id'];
        $dayOfWeek = $scheduleData['day_of_week'];
        $startTime = $scheduleData['start_time'];
        $endTime = $scheduleData['end_time'];

        // Check teacher conflicts
        $teacherConflicts = Schedule::where('school_id', $this->getCurrentSchoolId())
            ->conflictsWith($teacherId, $dayOfWeek, $startTime, $endTime)
            ->with(['subject'])
            ->get();

        if ($excludeId) {
            $teacherConflicts = $teacherConflicts->where('id', '!=', $excludeId);
        }

        if ($teacherConflicts->isNotEmpty()) {
            foreach ($teacherConflicts as $conflict) {
                $conflicts[] = "Teacher conflict with {$conflict->subject->name} ({$conflict->formatted_time})";
            }
        }

        // Check classroom conflicts (if classroom is specified)
        if (!empty($scheduleData['classroom'])) {
            $classroomConflicts = $this->checkClassroomConflicts(
                $scheduleData['classroom'], $dayOfWeek, $startTime, $endTime, $excludeId
            );

            if ($classroomConflicts->isNotEmpty()) {
                foreach ($classroomConflicts as $conflict) {
                    $conflicts[] = "Classroom conflict with {$conflict->subject->name} ({$conflict->formatted_time})";
                }
            }
        }

        return $conflicts;
    }

    public function generateLessonsForSchedule(Schedule $schedule): array
    {
        $lessons = $schedule->generateLessons();

        if (!empty($lessons)) {
            DB::table('lessons')->insert($lessons);
        }

        return $lessons;
    }

    public function detectAllConflicts(): Collection
    {
        $schedules = Schedule::where('school_id', $this->getCurrentSchoolId())
            ->where('status', 'active')
            ->with(['teacher'])
            ->get();

        $conflicts = collect();

        foreach ($schedules as $schedule) {
            $teacherConflicts = Schedule::where('school_id', $this->getCurrentSchoolId())
                ->conflictsWith(
                    $schedule->teacher_id,
                    $schedule->day_of_week,
                    $schedule->start_time,
                    $schedule->end_time
                )
                ->where('id', '!=', $schedule->id)
                ->get();

            if ($teacherConflicts->isNotEmpty()) {
                $conflict = $this->createConflictRecord(
                    'teacher_double_booking',
                    "Teacher {$schedule->teacher->full_name} has conflicting schedules",
                    array_merge([$schedule->id], $teacherConflicts->pluck('id')->toArray()),
                    $schedule->day_of_week,
                    $schedule->start_time,
                    $schedule->end_time
                );

                $conflicts->push($conflict);
            }
        }

        return $conflicts;
    }

    private function validateScheduleData(array $data): void
    {
        // Validate time format and logic
        $startTime = Carbon::createFromFormat('H:i', $data['start_time'])->seconds(0);
        $endTime = Carbon::createFromFormat('H:i', $data['end_time'])->seconds(0);
        Log::debug('ScheduleService validateScheduleData times', [
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'parsed_start' => $startTime->toTimeString(),
            'parsed_end' => $endTime->toTimeString(),
        ]);
        if ($startTime->gte($endTime)) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }

        // Validate date range
        if (Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        // Validate minimum lesson duration (e.g., 30 minutes)
        $duration = $startTime->diffInMinutes($endTime, false);
        Log::debug('ScheduleService validateScheduleData duration', [
            'duration_minutes' => $duration,
        ]);
        if ($duration < 30) {
            throw new \InvalidArgumentException('Lesson duration must be at least 30 minutes');
        }
    }

    private function checkClassroomConflicts(string $classroom, string $dayOfWeek, string $startTime, string $endTime, ?int $excludeId = null): Collection
    {
        $query = Schedule::where('school_id', $this->getCurrentSchoolId())
            ->where('classroom', $classroom)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'active')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<=', $startTime)
                         ->where('end_time', '>=', $endTime);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject'])->get();
    }

    private function createConflictRecord(string $type, string $description, array $scheduleIds, string $date, string $startTime, string $endTime): ScheduleConflict
    {
        return ScheduleConflict::create([
            'school_id' => $this->getCurrentSchoolId(),
            'conflict_type' => $type,
            'conflict_description' => $description,
            'conflicting_schedule_ids' => $scheduleIds,
            'affected_entities' => ['teacher_id' => $scheduleIds], // Simplified
            'conflict_date' => Carbon::now()->toDateString(), // For recurring conflicts
            'conflict_start_time' => $startTime,
            'conflict_end_time' => $endTime,
            'severity' => 'high',
            'status' => 'detected',
            'detection_method' => 'automatic'
        ]);
    }

    public function getScheduleStats(): array
    {
        $schoolId = $this->getCurrentSchoolId();

        return [
            'total_schedules' => Schedule::where('school_id', $schoolId)->count(),
            'active_schedules' => Schedule::where('school_id', $schoolId)->active()->count(),
            'total_lessons' => Lesson::where('school_id', $schoolId)->count(),
            'completed_lessons' => Lesson::where('school_id', $schoolId)->completed()->count(),
            'scheduled_lessons' => Lesson::where('school_id', $schoolId)->scheduled()->count(),
            'today_lessons' => Lesson::where('school_id', $schoolId)->today()->count(),
            'this_week_lessons' => Lesson::where('school_id', $schoolId)->thisWeek()->count(),
            'online_schedules' => Schedule::where('school_id', $schoolId)->where('is_online', true)->count(),
            'conflicts_detected' => ScheduleConflict::where('school_id', $schoolId)
                ->where('status', 'detected')
                ->count()
        ];
    }

    public function getWithFilters(array $filters)
    {
        $query = Schedule::where('school_id', $this->getCurrentSchoolId())
            ->with(['subject', 'class', 'teacher', 'academicYear']);

        // Apply filters
        if (!empty($filters['teacher_id'])) {
            $query->byTeacher($filters['teacher_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['day_of_week'])) {
            $query->byDay($filters['day_of_week']);
        }

        if (!empty($filters['period'])) {
            $query->byPeriod($filters['period']);
        }

        if (!empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('subject', function ($subjectQuery) use ($search) {
                      $subjectQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('class', function ($classQuery) use ($search) {
                      $classQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('teacher', function ($teacherQuery) use ($search) {
                      $teacherQuery->where('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}

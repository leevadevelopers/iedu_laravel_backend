<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\V1\Schedule\ScheduleConflict;
use App\Repositories\V1\Schedule\ScheduleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleService extends BaseScheduleService
{
    protected ScheduleRepository $scheduleRepository;

    public function __construct(ScheduleRepository $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
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
        $data['created_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $schedule = $this->scheduleRepository->create($data);

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

        $data['updated_by'] = auth()->id();
        return $this->scheduleRepository->update($schedule, $data);
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

        return $this->scheduleRepository->delete($schedule);
    }

    public function getTeacherSchedule(int $teacherId): Collection
    {
        return $this->scheduleRepository->getByTeacher($teacherId);
    }

    public function getClassSchedule(int $classId): Collection
    {
        return $this->scheduleRepository->getByClass($classId);
    }

    public function getWeeklySchedule(array $filters = []): Collection
    {
        return $this->scheduleRepository->getWeeklySchedule($filters);
    }

    public function checkConflicts(array $scheduleData, ?int $excludeId = null): array
    {
        $conflicts = [];

        $teacherId = $scheduleData['teacher_id'];
        $dayOfWeek = $scheduleData['day_of_week'];
        $startTime = $scheduleData['start_time'];
        $endTime = $scheduleData['end_time'];

        // Check teacher conflicts
        $teacherConflicts = $this->scheduleRepository->findConflicts(
            $teacherId, $dayOfWeek, $startTime, $endTime, $excludeId
        );

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
        $schedules = $this->scheduleRepository->schoolScoped()
            ->where('status', 'active')
            ->get();

        $conflicts = collect();

        foreach ($schedules as $schedule) {
            $teacherConflicts = $this->scheduleRepository->findConflicts(
                $schedule->teacher_id,
                $schedule->day_of_week,
                $schedule->start_time,
                $schedule->end_time,
                $schedule->id
            );

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
        if (Carbon::parse($data['start_time'])->gte(Carbon::parse($data['end_time']))) {
            throw new \InvalidArgumentException('Start time must be before end time');
        }

        // Validate date range
        if (Carbon::parse($data['start_date'])->gt(Carbon::parse($data['end_date']))) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }

        // Validate minimum lesson duration (e.g., 30 minutes)
        $duration = Carbon::parse($data['end_time'])->diffInMinutes(Carbon::parse($data['start_time']));
        if ($duration < 30) {
            throw new \InvalidArgumentException('Lesson duration must be at least 30 minutes');
        }
    }

    private function checkClassroomConflicts(string $classroom, string $dayOfWeek, string $startTime, string $endTime, ?int $excludeId = null): Collection
    {
        $query = $this->scheduleRepository->schoolScoped()
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
        return $this->scheduleRepository->getDashboardStats();
    }

    public function getScheduleRepository(): ScheduleRepository
    {
        return $this->scheduleRepository;
    }
}

#!/bin/bash

# iEDU Schedule & Lessons Management Module - Part 2
# Services, Controllers, Resources, Requests and Routes

echo "🔧 Creating Schedule & Lessons Services, Controllers and Resources..."

# =============================================================================
# 4. SERVICES
# =============================================================================

echo "⚙️ Creating services..."

mkdir -p app/Services/V1/Schedule

# 4.1 Base Schedule Service
cat > app/Services/V1/Schedule/BaseScheduleService.php << 'EOF'
<?php

namespace App\Services\V1\Schedule;

use App\Services\SchoolContextService;

abstract class BaseScheduleService
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    protected function getCurrentSchool()
    {
        return $this->schoolContextService->getCurrentSchool();
    }

    protected function getCurrentSchoolId(): int
    {
        return $this->getCurrentSchool()->id;
    }

    protected function validateSchoolOwnership($model): void
    {
        if ($model->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }
}
EOF

# 4.2 Schedule Service
cat > app/Services/V1/Schedule/ScheduleService.php << 'EOF'
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

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        ScheduleRepository $scheduleRepository
    ) {
        parent::__construct($schoolContextService);
        $this->scheduleRepository = $scheduleRepository;
    }

    public function createSchedule(array $data): Schedule
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

    public function updateSchedule(Schedule $schedule, array $data): Schedule
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
}
EOF

# 4.3 Lesson Service
cat > app/Services/V1/Schedule/LessonService.php << 'EOF'
<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use App\Repositories\V1\Schedule\LessonRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LessonService extends BaseScheduleService
{
    protected LessonRepository $lessonRepository;

    public function __construct(
        \App\Services\SchoolContextService $schoolContextService,
        LessonRepository $lessonRepository
    ) {
        parent::__construct($schoolContextService);
        $this->lessonRepository = $lessonRepository;
    }

    public function createLesson(array $data): Lesson
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['created_by'] = auth()->id();

        // Calculate duration
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $start = \Carbon\Carbon::parse($data['start_time']);
            $end = \Carbon\Carbon::parse($data['end_time']);
            $data['duration_minutes'] = $end->diffInMinutes($start);
        }

        return $this->lessonRepository->create($data);
    }

    public function updateLesson(Lesson $lesson, array $data): Lesson
    {
        $this->validateSchoolOwnership($lesson);

        $data['updated_by'] = auth()->id();

        // Recalculate duration if times changed
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $start = \Carbon\Carbon::parse($data['start_time']);
            $end = \Carbon\Carbon::parse($data['end_time']);
            $data['duration_minutes'] = $end->diffInMinutes($start);
        }

        return $this->lessonRepository->update($lesson, $data);
    }

    public function deleteLesson(Lesson $lesson): bool
    {
        $this->validateSchoolOwnership($lesson);

        // Check if lesson is in progress or completed
        if (in_array($lesson->status, ['in_progress', 'completed'])) {
            throw new \Exception('Cannot delete lesson that is in progress or completed');
        }

        return $this->lessonRepository->delete($lesson);
    }

    public function startLesson(Lesson $lesson): bool
    {
        $this->validateSchoolOwnership($lesson);

        if ($lesson->status !== 'scheduled') {
            throw new \Exception('Only scheduled lessons can be started');
        }

        return $lesson->update(['status' => 'in_progress']);
    }

    public function completeLesson(Lesson $lesson, array $data = []): bool
    {
        $this->validateSchoolOwnership($lesson);

        if (!in_array($lesson->status, ['scheduled', 'in_progress'])) {
            throw new \Exception('Only scheduled or in-progress lessons can be completed');
        }

        return $lesson->markAsCompleted($data);
    }

    public function cancelLesson(Lesson $lesson, string $reason = null): bool
    {
        $this->validateSchoolOwnership($lesson);

        if ($lesson->status === 'completed') {
            throw new \Exception('Cannot cancel a completed lesson');
        }

        return $lesson->cancel($reason);
    }

    public function addLessonContent(Lesson $lesson, array $contentData): \App\Models\V1\Schedule\LessonContent
    {
        $this->validateSchoolOwnership($lesson);

        $contentData['school_id'] = $this->getCurrentSchoolId();
        $contentData['lesson_id'] = $lesson->id;
        $contentData['uploaded_by'] = auth()->id();

        // Handle file upload
        if (isset($contentData['file']) && $contentData['file'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $contentData['file'];
            $path = $file->store('lesson-contents', 'public');

            $contentData['file_name'] = $file->getClientOriginalName();
            $contentData['file_path'] = $path;
            $contentData['file_type'] = $file->getClientOriginalExtension();
            $contentData['file_size'] = $file->getSize();
            $contentData['mime_type'] = $file->getMimeType();

            unset($contentData['file']);
        }

        return $lesson->contents()->create($contentData);
    }

    public function markAttendance(Lesson $lesson, array $attendanceData): Collection
    {
        $this->validateSchoolOwnership($lesson);

        $attendances = collect();

        DB::beginTransaction();
        try {
            foreach ($attendanceData as $studentData) {
                $attendance = LessonAttendance::updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'student_id' => $studentData['student_id']
                    ],
                    array_merge($studentData, [
                        'school_id' => $this->getCurrentSchoolId(),
                        'marked_by' => auth()->id()
                    ])
                );

                $attendances->push($attendance);
            }

            // Update lesson attendance stats
            $lesson->updateAttendanceStats();

            DB::commit();
            return $attendances;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function generateQRCode(Lesson $lesson): array
    {
        $this->validateSchoolOwnership($lesson);

        // Generate unique token for QR code
        $token = \Str::random(32);

        // Store token temporarily (you might want to use cache or database)
        \Cache::put("lesson_qr_{$lesson->id}", $token, now()->addMinutes(30));

        // Generate QR code URL
        $qrUrl = route('api.v1.lessons.check-in-qr', ['lesson' => $lesson->id, 'token' => $token]);

        return [
            'qr_url' => $qrUrl,
            'token' => $token,
            'expires_at' => now()->addMinutes(30)->toISOString()
        ];
    }

    public function checkInWithQR(Lesson $lesson, string $token, int $studentId): LessonAttendance
    {
        $this->validateSchoolOwnership($lesson);

        // Validate QR token
        $cachedToken = \Cache::get("lesson_qr_{$lesson->id}");
        if ($cachedToken !== $token) {
            throw new \Exception('Invalid or expired QR code');
        }

        // Mark attendance
        $attendance = LessonAttendance::updateOrCreate(
            [
                'lesson_id' => $lesson->id,
                'student_id' => $studentId
            ],
            [
                'school_id' => $this->getCurrentSchoolId(),
                'status' => 'present',
                'marked_by_method' => 'qr_code',
                'arrival_time' => now(),
                'marked_by' => $studentId // Student marked themselves
            ]
        );

        // Update lesson stats
        $lesson->updateAttendanceStats();

        return $attendance;
    }

    public function getTeacherLessons(int $teacherId, array $filters = []): Collection
    {
        return $this->lessonRepository->getByTeacher($teacherId, $filters);
    }

    public function getClassLessons(int $classId, array $filters = []): Collection
    {
        return $this->lessonRepository->getByClass($classId, $filters);
    }

    public function getUpcomingLessons(int $limit = 10, array $filters = []): Collection
    {
        return $this->lessonRepository->getUpcoming($limit, $filters);
    }

    public function getTodayLessons(array $filters = []): Collection
    {
        return $this->lessonRepository->getToday($filters);
    }

    public function getLessonStats(): array
    {
        return $this->lessonRepository->getDashboardStats();
    }

    public function getAttendanceStats(): array
    {
        return $this->lessonRepository->getAttendanceStats();
    }

    public function exportLessonReport(int $lessonId): array
    {
        $lesson = $this->lessonRepository->find($lessonId);
        $this->validateSchoolOwnership($lesson);

        $lesson->load([
            'subject',
            'class',
            'teacher',
            'attendances.student',
            'contents'
        ]);

        return [
            'lesson' => $lesson,
            'summary' => [
                'total_students' => $lesson->expected_students,
                'present_students' => $lesson->present_students,
                'attendance_rate' => $lesson->attendance_rate,
                'contents_count' => $lesson->contents->count(),
                'duration_minutes' => $lesson->duration_minutes
            ]
        ];
    }
}
EOF

# =============================================================================
# 5. CONTROLLERS
# =============================================================================

echo "🎮 Creating controllers..."

mkdir -p app/Http/Controllers/V1/Schedule

# 5.1 Schedule Controller
cat > app/Http/Controllers/V1/Schedule/ScheduleController.php << 'EOF'
<?php

namespace App\Http\Controllers\V1\Schedule;

use App\Http\Controllers\Controller;
use App\Models\V1\Schedule\Schedule;
use App\Services\V1\Schedule\ScheduleService;
use App\Http\Requests\V1\Schedule\StoreScheduleRequest;
use App\Http\Requests\V1\Schedule\UpdateScheduleRequest;
use App\Http\Resources\V1\Schedule\ScheduleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    protected ScheduleService $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'teacher_id', 'class_id', 'subject_id', 'period',
            'day_of_week', 'academic_year_id', 'is_online', 'status',
            'search', 'sort_by', 'sort_direction', 'per_page'
        ]);

        if ($request->has('weekly')) {
            $schedules = $this->scheduleService->getWeeklySchedule($filters);
            return response()->json([
                'data' => ScheduleResource::collection($schedules),
                'meta' => ['total' => $schedules->count()]
            ]);
        }

        $schedules = $this->scheduleService->getScheduleRepository()->getWithFilters($filters);

        return response()->json([
            'data' => ScheduleResource::collection($schedules),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total()
            ]
        ]);
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->createSchedule($request->validated());

            return response()->json([
                'message' => 'Schedule created successfully',
                'data' => new ScheduleResource($schedule->load(['subject', 'class', 'teacher']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Schedule $schedule): JsonResponse
    {
        return response()->json([
            'data' => new ScheduleResource($schedule->load([
                'subject', 'class', 'teacher', 'academicYear', 'lessons'
            ]))
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        try {
            $updatedSchedule = $this->scheduleService->updateSchedule($schedule, $request->validated());

            return response()->json([
                'message' => 'Schedule updated successfully',
                'data' => new ScheduleResource($updatedSchedule->load(['subject', 'class', 'teacher']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        try {
            $this->scheduleService->deleteSchedule($schedule);

            return response()->json([
                'message' => 'Schedule deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function teacherSchedule(Request $request): JsonResponse
    {
        $teacherId = $request->get('teacher_id', auth()->user()->teacher?->id);

        if (!$teacherId) {
            return response()->json(['message' => 'Teacher not found'], 404);
        }

        $schedules = $this->scheduleService->getTeacherSchedule($teacherId);

        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    public function classSchedule(Request $request): JsonResponse
    {
        $classId = $request->get('class_id');

        if (!$classId) {
            return response()->json(['message' => 'Class ID required'], 400);
        }

        $schedules = $this->scheduleService->getClassSchedule($classId);

        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    public function generateLessons(Schedule $schedule): JsonResponse
    {
        try {
            $lessons = $this->scheduleService->generateLessonsForSchedule($schedule);

            return response()->json([
                'message' => 'Lessons generated successfully',
                'data' => [
                    'lessons_created' => count($lessons),
                    'schedule' => new ScheduleResource($schedule)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate lessons',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function checkConflicts(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'classroom' => 'nullable|string'
        ]);

        try {
            $conflicts = $this->scheduleService->checkConflicts($request->only([
                'teacher_id', 'day_of_week', 'start_time', 'end_time', 'classroom'
            ]));

            return response()->json([
                'has_conflicts' => !empty($conflicts),
                'conflicts' => $conflicts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check conflicts',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function stats(): JsonResponse
    {
        $stats = $this->scheduleService->getScheduleStats();

        return response()->json([
            'data' => $stats
        ]);
    }
}
EOF

# 5.2 Lesson Controller
cat > app/Http/Controllers/V1/Schedule/LessonController.php << 'EOF'
<?php

namespace App\Http\Controllers\V1\Schedule;

use App\Http\Controllers\Controller;
use App\Models\V1\Schedule\Lesson;
use App\Services\V1\Schedule\LessonService;
use App\Http\Requests\V1\Schedule\StoreLessonRequest;
use App\Http\Requests\V1\Schedule\UpdateLessonRequest;
use App\Http\Resources\V1\Schedule\LessonResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    protected LessonService $lessonService;

    public function __construct(LessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'teacher_id', 'class_id', 'subject_id', 'type', 'status',
            'date_from', 'date_to', 'is_online',
            'search', 'sort_by', 'sort_direction', 'per_page'
        ]);

        // Handle special views
        if ($request->has('view')) {
            switch ($request->get('view')) {
                case 'upcoming':
                    $lessons = $this->lessonService->getUpcomingLessons($request->get('limit', 10), $filters);
                    return response()->json(['data' => LessonResource::collection($lessons)]);

                case 'today':
                    $lessons = $this->lessonService->getTodayLessons($filters);
                    return response()->json(['data' => LessonResource::collection($lessons)]);

                case 'teacher':
                    $teacherId = $request->get('teacher_id', auth()->user()->teacher?->id);
                    $lessons = $this->lessonService->getTeacherLessons($teacherId, $filters);
                    return response()->json(['data' => LessonResource::collection($lessons)]);

                case 'class':
                    if (!$request->has('class_id')) {
                        return response()->json(['message' => 'Class ID required'], 400);
                    }
                    $lessons = $this->lessonService->getClassLessons($request->get('class_id'), $filters);
                    return response()->json(['data' => LessonResource::collection($lessons)]);
            }
        }

        $lessons = $this->lessonService->getLessonRepository()->getWithFilters($filters);

        return response()->json([
            'data' => LessonResource::collection($lessons),
            'meta' => [
                'current_page' => $lessons->currentPage(),
                'last_page' => $lessons->lastPage(),
                'per_page' => $lessons->perPage(),
                'total' => $lessons->total()
            ]
        ]);
    }

    public function store(StoreLessonRequest $request): JsonResponse
    {
        try {
            $lesson = $this->lessonService->createLesson($request->validated());

            return response()->json([
                'message' => 'Lesson created successfully',
                'data' => new LessonResource($lesson->load(['subject', 'class', 'teacher']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Lesson $lesson): JsonResponse
    {
        return response()->json([
            'data' => new LessonResource($lesson->load([
                'subject', 'class', 'teacher', 'schedule', 'contents', 'attendances.student'
            ]))
        ]);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        try {
            $updatedLesson = $this->lessonService->updateLesson($lesson, $request->validated());

            return response()->json([
                'message' => 'Lesson updated successfully',
                'data' => new LessonResource($updatedLesson->load(['subject', 'class', 'teacher']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        try {
            $this->lessonService->deleteLesson($lesson);

            return response()->json([
                'message' => 'Lesson deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function start(Lesson $lesson): JsonResponse
    {
        try {
            $this->lessonService->startLesson($lesson);

            return response()->json([
                'message' => 'Lesson started successfully',
                'data' => new LessonResource($lesson->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'content_summary' => 'nullable|string|max:2000',
            'homework_assigned' => 'nullable|string|max:1000',
            'homework_due_date' => 'nullable|date|after:today',
            'teacher_notes' => 'nullable|string|max:1000'
        ]);

        try {
            $this->lessonService->completeLesson($lesson, $request->only([
                'content_summary', 'homework_assigned', 'homework_due_date', 'teacher_notes'
            ]));

            return response()->json([
                'message' => 'Lesson completed successfully',
                'data' => new LessonResource($lesson->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function cancel(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $this->lessonService->cancelLesson($lesson, $request->get('reason'));

            return response()->json([
                'message' => 'Lesson cancelled successfully',
                'data' => new LessonResource($lesson->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel lesson',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function markAttendance(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused,left_early,partial,online_present',
            'attendance.*.notes' => 'nullable|string|max:255',
            'attendance.*.arrival_time' => 'nullable|date_format:H:i',
            'attendance.*.minutes_late' => 'nullable|integer|min:0'
        ]);

        try {
            $attendances = $this->lessonService->markAttendance($lesson, $request->get('attendance'));

            return response()->json([
                'message' => 'Attendance marked successfully',
                'data' => [
                    'lesson' => new LessonResource($lesson->fresh()),
                    'attendances_count' => $attendances->count(),
                    'present_count' => $attendances->where('status', 'present')->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark attendance',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function generateQR(Lesson $lesson): JsonResponse
    {
        try {
            $qrData = $this->lessonService->generateQRCode($lesson);

            return response()->json([
                'message' => 'QR code generated successfully',
                'data' => $qrData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function checkInQR(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'student_id' => 'required|exists:students,id'
        ]);

        try {
            $attendance = $this->lessonService->checkInWithQR(
                $lesson,
                $request->get('token'),
                $request->get('student_id')
            );

            return response()->json([
                'message' => 'Check-in successful',
                'data' => [
                    'attendance' => $attendance,
                    'lesson' => new LessonResource($lesson->fresh())
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Check-in failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function addContent(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content_type' => 'required|in:document,video,audio,link,image,presentation,worksheet,quiz,assignment,meeting_recording,live_stream,external_resource',
            'file' => 'nullable|file|max:50000', // 50MB
            'url' => 'nullable|url',
            'category' => 'nullable|string|max:100',
            'is_required' => 'boolean',
            'is_downloadable' => 'boolean',
            'is_public' => 'boolean'
        ]);

        try {
            $content = $this->lessonService->addLessonContent($lesson, $request->all());

            return response()->json([
                'message' => 'Content added successfully',
                'data' => $content
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add content',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function stats(): JsonResponse
    {
        $stats = $this->lessonService->getLessonStats();

        return response()->json([
            'data' => $stats
        ]);
    }

    public function attendanceStats(): JsonResponse
    {
        $stats = $this->lessonService->getAttendanceStats();

        return response()->json([
            'data' => $stats
        ]);
    }

    public function exportReport(Lesson $lesson): JsonResponse
    {
        try {
            $report = $this->lessonService->exportLessonReport($lesson->id);

            return response()->json([
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
EOF

# =============================================================================
# 6. RESOURCES
# =============================================================================

echo "📦 Creating resources..."

mkdir -p app/Http/Resources/V1/Schedule

# 6.1 Base Schedule Resource
cat > app/Http/Resources/V1/Schedule/BaseScheduleResource.php << 'EOF'
<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseScheduleResource extends JsonResource
{
    protected function formatDateTime($datetime): ?string
    {
        return $datetime ? $datetime->format('Y-m-d H:i:s') : null;
    }

    protected function formatDate($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    protected function formatTime($time): ?string
    {
        return $time ? $time->format('H:i') : null;
    }

    protected function whenLoaded(string $relation, $value = null)
    {
        return $this->resource->relationLoaded($relation) ?
            ($value ?? $this->resource->{$relation}) :
            $this->missingValue();
    }

    protected function addMetadata(array $data): array
    {
        return array_merge($data, [
            'meta' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
                'school_id' => $this->school_id,
            ]
        ]);
    }
}
EOF

# 6.2 Schedule Resource
cat > app/Http/Resources/V1/Schedule/ScheduleResource.php << 'EOF'
<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class ScheduleResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            // Associations
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'academic_year_id' => $this->academic_year_id,
            'academic_term_id' => $this->academic_term_id,
            'classroom' => $this->classroom,

            // Timing
            'period' => $this->period,
            'period_label' => $this->period_label,
            'day_of_week' => $this->day_of_week,
            'day_of_week_label' => $this->day_of_week_label,
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'formatted_time' => $this->formatted_time,
            'duration_in_minutes' => $this->duration_in_minutes,

            // Date range
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'recurrence_pattern' => $this->recurrence_pattern,

            // Configuration
            'status' => $this->status,
            'is_online' => $this->is_online,
            'online_meeting_url' => $this->when($this->is_online, $this->online_meeting_url),
            'configuration_json' => $this->configuration_json,

            // Relationships
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code
                ];
            }),

            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                    'grade_level' => $this->class->grade_level
                ];
            }),

            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'full_name' => $this->teacher->full_name,
                    'display_name' => $this->teacher->display_name
                ];
            }),

            'academic_year' => $this->whenLoaded('academicYear', function () {
                return [
                    'id' => $this->academicYear->id,
                    'name' => $this->academicYear->name
                ];
            }),

            'lessons_count' => $this->whenLoaded('lessons', $this->lessons->count()),

            // Audit
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ]);
    }
}
EOF

# 6.3 Lesson Resource
cat > app/Http/Resources/V1/Schedule/LessonResource.php << 'EOF'
<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class LessonResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return $this->addMetadata([
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,

            // Basic info
            'title' => $this->title,
            'description' => $this->description,
            'objectives' => $this->objectives,

            // Associations
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'teacher_id' => $this->teacher_id,
            'academic_term_id' => $this->academic_term_id,

            // Timing
            'lesson_date' => $this->formatDate($this->lesson_date),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'formatted_time' => $this->formatted_time,
            'duration_minutes' => $this->duration_minutes,

            // Location and format
            'classroom' => $this->classroom,
            'is_online' => $this->is_online,
            'online_meeting_url' => $this->when($this->is_online, $this->online_meeting_url),
            'online_meeting_details' => $this->when($this->is_online, $this->online_meeting_details),

            // Status and type
            'status' => $this->status,
            'status_label' => $this->status_label,
            'type' => $this->type,
            'type_label' => $this->type_label,

            // Content and curriculum
            'content_summary' => $this->content_summary,
            'curriculum_topics' => $this->curriculum_topics,
            'homework_assigned' => $this->homework_assigned,
            'homework_due_date' => $this->formatDate($this->homework_due_date),

            // Attendance
            'expected_students' => $this->expected_students,
            'present_students' => $this->present_students,
            'attendance_rate' => $this->attendance_rate,

            // Teacher notes
            'teacher_notes' => $this->teacher_notes,
            'lesson_observations' => $this->lesson_observations,
            'student_participation' => $this->student_participation,

            // Flags
            'is_today' => $this->isToday(),
            'is_past' => $this->isPast(),
            'is_future' => $this->isFuture(),
            'has_homework' => $this->hasHomework(),
            'has_contents' => $this->hasContents(),

            // Approval
            'requires_approval' => $this->requires_approval,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->formatDateTime($this->approved_at),

            // Relationships
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code
                ];
            }),

            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                    'grade_level' => $this->class->grade_level,
                    'current_enrollment' => $this->class->current_enrollment
                ];
            }),

            'teacher' => $this->whenLoaded('teacher', function () {
                return [
                    'id' => $this->teacher->id,
                    'full_name' => $this->teacher->full_name,
                    'display_name' => $this->teacher->display_name
                ];
            }),

            'schedule' => $this->whenLoaded('schedule', function () {
                return [
                    'id' => $this->schedule->id,
                    'name' => $this->schedule->name,
                    'day_of_week' => $this->schedule->day_of_week,
                    'period' => $this->schedule->period
                ];
            }),

            'contents' => $this->whenLoaded('contents', LessonContentResource::collection($this->contents)),

            'attendances_summary' => $this->whenLoaded('attendances', function () {
                return [
                    'total' => $this->attendances->count(),
                    'present' => $this->attendances->where('status', 'present')->count(),
                    'absent' => $this->attendances->where('status', 'absent')->count(),
                    'late' => $this->attendances->where('status', 'late')->count(),
                    'excused' => $this->attendances->where('status', 'excused')->count()
                ];
            }),

            // Audit
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ]);
    }
}
EOF

# 6.4 Lesson Content Resource
cat > app/Http/Resources/V1/Schedule/LessonContentResource.php << 'EOF'
<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class LessonContentResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,

            // Content info
            'title' => $this->title,
            'description' => $this->description,
            'content_type' => $this->content_type,
            'content_type_label' => $this->content_type_label,
            'icon_class' => $this->getIconClass(),

            // File information
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->getFileSizeFormatted(),
            'mime_type' => $this->mime_type,
            'file_url' => $this->getFileUrl(),
            'download_url' => $this->getDownloadUrl(),

            // URL information
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'embed_data' => $this->embed_data,

            // Organization
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_required' => $this->is_required,
            'is_downloadable' => $this->is_downloadable,
            'is_public' => $this->is_public,

            // Access control
            'available_from' => $this->formatDate($this->available_from),
            'available_until' => $this->formatDate($this->available_until),
            'is_available' => $this->isAvailable(),

            // Metadata
            'metadata' => $this->metadata,
            'notes' => $this->notes,
            'tags' => $this->tags,

            // Flags
            'is_file' => $this->isFile(),
            'is_url' => $this->isUrl(),

            // Audit
            'uploaded_by' => $this->uploaded_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
EOF

echo "✅ Schedule & Lessons Services, Controllers and Resources created successfully!"
echo ""
echo "📋 Components created:"
echo "   ⚙️ Services: BaseScheduleService, ScheduleService, LessonService"
echo "   🎮 Controllers: ScheduleController, LessonController"
echo "   📦 Resources: BaseScheduleResource, ScheduleResource, LessonResource, LessonContentResource"
echo ""
echo "🔄 Next: Create Requests and Routes to complete the module"

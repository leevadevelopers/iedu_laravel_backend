<?php

namespace App\Http\Controllers\API\V1\Schedule;

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

        $schedules = $this->scheduleService->getWithFilters($filters);

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
        $teacherId = $request->get('teacher_id', auth('api')->user()->teacher?->id);

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

    /**
     * Teacher Dashboard - Get teacher's dashboard data
     */
    public function teacherDashboard(): JsonResponse
    {
        // Verificar se o usuário é um professor
        $user = auth('api')->user();
        if (!$user->hasRole('teacher') && !$user->teacher) {
            return response()->json(['message' => 'Access denied. Teacher role required.'], 403);
        }

        $teacherId = $user->teacher->id;
        $lessonService = app(\App\Services\V1\Schedule\LessonService::class);

        return response()->json([
            'today_lessons' => $lessonService->getTodayLessons(['teacher_id' => $teacherId]),
            'upcoming_lessons' => $lessonService->getUpcomingLessons(5, ['teacher_id' => $teacherId]),
            'stats' => $lessonService->getLessonStats()
        ]);
    }

    /**
     * Student Schedule - Get student's schedule
     */
    public function studentSchedule(): JsonResponse
    {
        // Verificar se o usuário é um estudante
        $user = auth('api')->user();
        if (!$user->hasRole('student') && !$user->student) {
            return response()->json(['message' => 'Access denied. Student role required.'], 403);
        }

        $student = $user->student;
        $schedules = Schedule::whereHas('class.students', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })->with(['subject', 'teacher'])->get();

        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    /**
     * Parent Children Schedule - Get parent's children schedules
     */
    public function parentChildrenSchedule(): JsonResponse
    {
        // Verificar se o usuário é um pai/mãe
        $user = auth('api')->user();
        if (!$user->hasRole('parent')) {
            return response()->json(['message' => 'Access denied. Parent role required.'], 403);
        }

        $children = $user->students; // Assuming relationship exists

        if ($children->isEmpty()) {
            return response()->json(['message' => 'No children found'], 404);
        }

        $schedules = Schedule::whereHas('class.students', function ($query) use ($children) {
            $query->whereIn('student_id', $children->pluck('id'));
        })->with(['subject', 'teacher', 'class'])->get();

        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\Controller;
use App\Models\V1\Schedule\Schedule;
use App\Models\V1\SIS\School\School;
use App\Services\V1\Schedule\ScheduleService;
use App\Http\Requests\V1\Schedule\StoreScheduleRequest;
use App\Http\Requests\V1\Schedule\UpdateScheduleRequest;
use App\Http\Resources\V1\Schedule\ScheduleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    protected ScheduleService $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try getCurrentSchool method first (preferred)
        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        // Fallback to school_id attribute
        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        // Try activeSchools relationship
        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
    }

    /**
     * Get the current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Verify that a school_id belongs to the user's tenant
     */
    protected function verifySchoolAccess(int $schoolId): bool
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return false;
        }

        // Check if school belongs to user's tenant
        $school = School::where('id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->exists();

        return $school;
    }

    /**
     * Verify schedule access (tenant and school)
     */
    protected function verifyScheduleAccess(Schedule $schedule): bool
    {
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return false;
        }

        // Check tenant ownership
        if (isset($schedule->tenant_id) && $schedule->tenant_id !== $tenantId) {
            return false;
        }

        // Check school ownership
        if ($schedule->school_id !== $schoolId) {
            return false;
        }

        // Verify school belongs to tenant
        return $this->verifySchoolAccess($schedule->school_id);
    }

    public function index(Request $request): JsonResponse
    {
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

        // Verify school access if school_id is provided in request
        if ($request->has('school_id')) {
            $requestedSchoolId = $request->school_id;
            if (!$this->verifySchoolAccess($requestedSchoolId)) {
                return response()->json([
                    'message' => 'You do not have access to this school'
                ], 403);
            }
        }

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
        // Verify schedule access
        if (!$this->verifyScheduleAccess($schedule)) {
            return response()->json([
                'message' => 'Access denied. Schedule does not belong to your tenant or school.'
            ], 403);
        }

        return response()->json([
            'data' => new ScheduleResource($schedule->load([
                'subject', 'class', 'teacher', 'academicYear', 'lessons'
            ]))
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        // Verify schedule access
        if (!$this->verifyScheduleAccess($schedule)) {
            return response()->json([
                'message' => 'Access denied. Schedule does not belong to your tenant or school.'
            ], 403);
        }

        try {
            // Get data from JSON request - try multiple methods
            $allData = [];

            // Method 1: Use Laravel's json() method (preferred)
            if ($request->isJson()) {
                try {
                    $json = $request->json();
                    if ($json) {
                        $allData = $json->all();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get JSON via json() method', ['error' => $e->getMessage()]);
                }
            }

            // Method 2: Decode content manually (handle trailing comma)
            if (empty($allData)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    // Remove trailing comma before closing brace
                    $content = preg_replace('/,\s*}[\s\n]*$/', '}', $content);
                    $content = preg_replace('/,\s*][\s\n]*$/', ']', $content);

                    $decoded = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $allData = $decoded;
                    } else {
                        Log::warning('JSON decode failed', [
                            'error' => json_last_error_msg(),
                            'content_preview' => substr($content, 0, 200)
                        ]);
                    }
                }
            }

            // Method 3: Try getAllData() from request
            if (empty($allData) && method_exists($request, 'getAllData')) {
                $allData = $request->getAllData();
            }

            // Method 4: Final fallback
            if (empty($allData)) {
                $allData = $request->all();
            }

            // Define allowed fields that can be updated
            $allowedFields = [
                'name', 'description', 'subject_id', 'class_id', 'teacher_id',
                'academic_year_id', 'academic_term_id', 'classroom', 'period',
                'day_of_week', 'start_time', 'end_time', 'start_date', 'end_date',
                'recurrence_pattern', 'status', 'is_online', 'online_meeting_url',
                'configuration_json'
            ];

            // Filter only allowed fields and remove null values and computed fields
            $updateData = [];
            foreach ($allowedFields as $field) {
                if (isset($allData[$field]) && $allData[$field] !== null) {
                    $updateData[$field] = $allData[$field];
                }
            }

            // Remove computed/accessor fields that shouldn't be updated
            unset($updateData['period_label'], $updateData['day_of_week_label']);

            Log::debug('Schedule update request data', [
                'all_data' => $allData,
                'filtered_update_data' => $updateData,
                'content_type' => $request->header('Content-Type'),
                'is_json' => $request->isJson(),
                'content' => substr($request->getContent(), 0, 500),
                'method' => $request->method()
            ]);

            if (empty($updateData)) {
                return response()->json([
                    'message' => 'No valid data provided for update',
                    'debug' => [
                        'all_data' => $allData,
                        'request_all' => $request->all(),
                        'request_json' => $request->isJson() ? json_decode($request->getContent(), true) : null,
                        'content' => substr($request->getContent(), 0, 500)
                    ]
                ], 422);
            }

            $updatedSchedule = $this->scheduleService->updateSchedule($schedule, $updateData);

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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

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
        // Verify schedule access
        if (!$this->verifyScheduleAccess($schedule)) {
            return response()->json([
                'message' => 'Access denied. Schedule does not belong to your tenant or school.'
            ], 403);
        }

        try {
            $result = $this->scheduleService->generateLessonsForSchedule($schedule);

            $message = 'Lessons generated successfully';
            if ($result['existing_lessons'] > 0) {
                $message = sprintf(
                    '%d %s criada%s, %d já existia%s',
                    $result['created_lessons'],
                    $result['created_lessons'] === 1 ? 'aula' : 'aulas',
                    $result['created_lessons'] === 1 ? '' : 's',
                    $result['existing_lessons'],
                    $result['existing_lessons'] === 1 ? 'm' : 'm'
                );
            } elseif ($result['created_lessons'] > 0) {
                $message = sprintf(
                    '%d %s gerada%s com sucesso',
                    $result['created_lessons'],
                    $result['created_lessons'] === 1 ? 'aula' : 'aulas',
                    $result['created_lessons'] === 1 ? '' : 's'
                );
            } else {
                $message = 'Nenhuma nova aula gerada (todas já existem)';
            }

            return response()->json([
                'message' => $message,
                'data' => [
                    'lessons_created' => $result['created_lessons'],
                    'lessons_existing' => $result['existing_lessons'],
                    'lessons_total' => $result['total_lessons'],
                    'schedule' => new ScheduleResource($schedule->fresh())
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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

        // Verificar se o usuário é um estudante
        $user = auth('api')->user();
        if (!$user->hasRole('student') && !$user->student) {
            return response()->json(['message' => 'Access denied. Student role required.'], 403);
        }

        $student = $user->student;
        $schedules = Schedule::where('tenant_id', $tenantId)
            ->where('school_id', $schoolId)
            ->whereHas('class.students', function ($query) use ($student) {
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
        // Verify tenant and school access
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        if (!$tenantId || !$schoolId) {
            return response()->json([
                'message' => 'User is not associated with any tenant or school'
            ], 403);
        }

        // Verificar se o usuário é um pai/mãe
        $user = auth('api')->user();
        if (!$user->hasRole('parent')) {
            return response()->json(['message' => 'Access denied. Parent role required.'], 403);
        }

        $children = $user->students; // Assuming relationship exists

        if ($children->isEmpty()) {
            return response()->json(['message' => 'No children found'], 404);
        }

        $schedules = Schedule::where('tenant_id', $tenantId)
            ->where('school_id', $schoolId)
            ->whereHas('class.students', function ($query) use ($children) {
                $query->whereIn('student_id', $children->pluck('id'));
            })->with(['subject', 'teacher', 'class'])->get();

        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    /**
     * Validate schedule conflict with detailed information
     */
    public function validateConflict(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:classes,id',
            'classroom' => 'nullable|string',
            'day_of_week' => 'required|string',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'exclude_schedule_id' => 'nullable|exists:schedules,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $scheduleData = $request->only([
                'teacher_id', 'class_id', 'classroom', 'day_of_week', 'start_time', 'end_time'
            ]);
            $excludeScheduleId = $request->exclude_schedule_id;

            $conflicts = $this->scheduleService->checkConflicts($scheduleData, $excludeScheduleId);

            // Format conflicts for frontend
            $conflictDetails = [];
            $hasConflict = !empty($conflicts);

            if ($hasConflict) {
                // Parse conflict messages to extract details
                foreach ($conflicts as $conflictMessage) {
                    if (strpos($conflictMessage, 'Teacher conflict') !== false) {
                        $conflictDetails[] = [
                            'type' => 'teacher',
                            'message' => $conflictMessage,
                            'severity' => 'error'
                        ];
                    } elseif (strpos($conflictMessage, 'Classroom conflict') !== false) {
                        $conflictDetails[] = [
                            'type' => 'classroom',
                            'message' => $conflictMessage,
                            'severity' => 'warning'
                        ];
                    } elseif (strpos($conflictMessage, 'Class conflict') !== false) {
                        $conflictDetails[] = [
                            'type' => 'class',
                            'message' => $conflictMessage,
                            'severity' => 'error'
                        ];
                    } else {
                        $conflictDetails[] = [
                            'type' => 'other',
                            'message' => $conflictMessage,
                            'severity' => 'warning'
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'hasConflict' => $hasConflict,
                'conflicts' => $conflictDetails
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate conflict',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

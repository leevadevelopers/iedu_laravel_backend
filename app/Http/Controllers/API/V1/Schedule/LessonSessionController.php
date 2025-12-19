<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\Controller;
use App\Models\V1\Schedule\LessonSession;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\Schedule\BehaviorRecord;
use App\Models\V1\Schedule\Schedule;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LessonSessionController extends Controller
{
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

        // Try session or header
        $tenantId = session('tenant_id');
        if (!$tenantId && request()->hasHeader('X-Tenant-ID')) {
            $tenantId = (int) request()->header('X-Tenant-ID');
        }

        return $tenantId;
    }
    public function index(Request $request): JsonResponse
    {
        $query = LessonSession::with(['teacher', 'subject', 'class', 'schedule']);

        // Filters
        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        // For teachers, show only their sessions
        if ($request->has('view') && $request->view === 'teacher') {
            $teacherId = Auth::user()->teacher?->id ?? $request->teacher_id;
            if ($teacherId) {
                $query->where('teacher_id', $teacherId);
            }
        }

        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('started_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total()
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'nullable|exists:schedules,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'is_scheduled' => 'boolean',
            'device_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            // Get school_id and teacher_id: prefer from schedule, then from class, then from user context
            if (!empty($data['schedule_id'])) {
                $schedule = Schedule::withoutTenantScope()->find($data['schedule_id']);
                if ($schedule) {
                    if ($schedule->school_id) {
                        $data['school_id'] = $schedule->school_id;
                    }
                    if ($schedule->teacher_id && empty($data['teacher_id'])) {
                        $data['teacher_id'] = $schedule->teacher_id;
                    }
                    // Also get subject_id and class_id from schedule if not provided
                    if (empty($data['subject_id']) && $schedule->subject_id) {
                        $data['subject_id'] = $schedule->subject_id;
                    }
                    if (empty($data['class_id']) && $schedule->class_id) {
                        $data['class_id'] = $schedule->class_id;
                    }
                }
            }
            
            // Get teacher_id from request or authenticated user (if not already set from schedule)
            if (empty($data['teacher_id'])) {
                $user = Auth::user();
                // Query Teacher model directly since User model doesn't have teacher() relationship
                $teacher = Teacher::where('user_id', $user->id)->first();
                if ($teacher) {
                    $data['teacher_id'] = $teacher->id;
                }
            }
            
            // Get school_id from class if not already set
            if (empty($data['school_id']) && !empty($data['class_id'])) {
                $class = AcademicClass::withoutTenantScope()->find($data['class_id']);
                if ($class && $class->school_id) {
                    $data['school_id'] = $class->school_id;
                }
            }
            
            // Get school_id from user context if not already set
            if (empty($data['school_id'])) {
                $data['school_id'] = $this->getCurrentSchoolId();
            }
            
            if (empty($data['school_id']) && $request->has('school_id')) {
                $data['school_id'] = $request->school_id;
            }
            
            // Validate required fields
            if (empty($data['teacher_id'])) {
                return response()->json([
                    'message' => 'Teacher ID is required. Please provide teacher_id, start from a schedule with a teacher, or ensure your user account is linked to a teacher.',
                    'errors' => ['teacher_id' => ['Teacher ID is required']]
                ], 422);
            }
            
            if (empty($data['school_id'])) {
                return response()->json([
                    'message' => 'School ID is required. Unable to determine school from schedule, class, or user context.',
                    'errors' => ['school_id' => ['School ID is required']]
                ], 422);
            }
            
            $data['started_at'] = now();
            $data['status'] = 'in_progress';
            $data['is_scheduled'] = $data['is_scheduled'] ?? ($data['schedule_id'] !== null);
            
            // Get tenant_id: prefer from schedule, then from class, then from user context
            if (!empty($data['schedule_id'])) {
                // Use withoutTenantScope to ensure we can load the schedule
                $schedule = Schedule::withoutTenantScope()->find($data['schedule_id']);
                if ($schedule && isset($schedule->tenant_id) && $schedule->tenant_id) {
                    $data['tenant_id'] = $schedule->tenant_id;
                }
            }
            
            if (empty($data['tenant_id']) && !empty($data['class_id'])) {
                // Use withoutTenantScope to ensure we can load the class
                $class = AcademicClass::withoutTenantScope()->find($data['class_id']);
                if ($class && isset($class->tenant_id) && $class->tenant_id) {
                    $data['tenant_id'] = $class->tenant_id;
                }
            }
            
            if (empty($data['tenant_id'])) {
                $data['tenant_id'] = $this->getCurrentTenantId();
            }
            
            if (empty($data['tenant_id']) && $request->has('tenant_id')) {
                $data['tenant_id'] = $request->tenant_id;
            }
            
            // Final fallback: try to get from session or header
            if (empty($data['tenant_id'])) {
                $tenantId = session('tenant_id');
                if (!$tenantId && $request->hasHeader('X-Tenant-ID')) {
                    $tenantId = (int) $request->header('X-Tenant-ID');
                }
                if ($tenantId) {
                    $data['tenant_id'] = $tenantId;
                }
            }
            
            if (empty($data['tenant_id'])) {
                return response()->json([
                    'message' => 'Tenant ID is required. Unable to determine tenant from schedule, class, user context, session, or headers.',
                    'errors' => ['tenant_id' => ['Tenant ID is required']],
                    'debug' => [
                        'schedule_id' => $data['schedule_id'] ?? null,
                        'class_id' => $data['class_id'] ?? null,
                        'getCurrentTenantId' => $this->getCurrentTenantId(),
                        'session_tenant_id' => session('tenant_id'),
                        'header_tenant_id' => $request->header('X-Tenant-ID')
                    ]
                ], 422);
            }
            
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $session = LessonSession::create($data);

            return response()->json([
                'message' => 'Lesson session started successfully',
                'data' => $session->load(['teacher', 'subject', 'class', 'schedule'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to start lesson session',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function show(LessonSession $lessonSession): JsonResponse
    {
        $lessonSession->load([
            'teacher', 'subject', 'class', 'schedule',
            'attendanceRecords.student',
            'behaviorRecords.student'
        ]);

        return response()->json([
            'data' => $lessonSession
        ]);
    }

    public function update(Request $request, LessonSession $lessonSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lesson_note' => 'nullable|string|max:500',
            'lesson_tags' => 'nullable|array',
            'lesson_tags.*' => 'string|in:new_topic,review,homework,test',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['updated_by'] = Auth::id();

            $lessonSession->update($data);

            return response()->json([
                'message' => 'Lesson session updated successfully',
                'data' => $lessonSession->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update lesson session',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function complete(Request $request, LessonSession $lessonSession): JsonResponse
    {
        if ($lessonSession->status !== 'in_progress') {
            return response()->json([
                'message' => 'Lesson session is not in progress'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $lessonSession->complete();

            DB::commit();

            return response()->json([
                'message' => 'Lesson session completed successfully',
                'data' => $lessonSession->fresh()->load(['teacher', 'subject', 'class'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to complete lesson session',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function markAttendance(Request $request, LessonSession $lessonSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused,unmarked',
            'attendance.*.note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $attendanceData = $validator->validated()['attendance'];
            $markedBy = Auth::id();
            
            // Load schedule relationship if it exists
            $lessonSession->load('schedule');
            
            // Get lesson_id from schedule if available (schedule might have a lesson relationship)
            $lessonId = null;
            if ($lessonSession->schedule_id && $lessonSession->schedule) {
                // Try to get lesson_id from schedule's lesson relationship if it exists
                if (isset($lessonSession->schedule->lesson_id)) {
                    $lessonId = $lessonSession->schedule->lesson_id;
                }
                // Alternative: if schedule has a lessons relationship, get the first lesson
                elseif (method_exists($lessonSession->schedule, 'lessons') && $lessonSession->schedule->lessons) {
                    $firstLesson = $lessonSession->schedule->lessons()->first();
                    if ($firstLesson) {
                        $lessonId = $firstLesson->id;
                    }
                }
            }

            foreach ($attendanceData as $attendance) {
                LessonAttendance::updateOrCreate(
                    [
                        'lesson_session_id' => $lessonSession->id,
                        'student_id' => $attendance['student_id']
                    ],
                    [
                        'school_id' => $lessonSession->school_id,
                        'lesson_id' => $lessonId, // Can be null now
                        'status' => $attendance['status'] === 'unmarked' ? null : $attendance['status'],
                        'notes' => $attendance['note'] ?? null,
                        'marked_by' => $markedBy,
                        'marked_by_method' => 'teacher_manual',
                        'updated_by' => $markedBy,
                    ]
                );
            }

            // Recalculate stats
            $lessonSession->calculateStats();

            DB::commit();

            return response()->json([
                'message' => 'Attendance marked successfully',
                'data' => $lessonSession->fresh()->load('attendanceRecords.student')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to mark attendance',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getAttendance(LessonSession $lessonSession): JsonResponse
    {
        $attendance = $lessonSession->attendanceRecords()
            ->with('student')
            ->get();

        return response()->json([
            'data' => $attendance
        ]);
    }

    public function addBehaviorPoint(Request $request, LessonSession $lessonSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'points' => 'required|integer|between:-10,10',
            'category' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['tenant_id'] = $lessonSession->tenant_id;
            $data['school_id'] = $lessonSession->school_id;
            $data['lesson_session_id'] = $lessonSession->id;
            $data['recorded_at'] = now();
            $data['recorded_by'] = Auth::id();
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();

            $behaviorRecord = BehaviorRecord::create($data);

            // Recalculate stats
            $lessonSession->calculateStats();

            return response()->json([
                'message' => 'Behavior point added successfully',
                'data' => $behaviorRecord->load('student')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add behavior point',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function getBehavior(LessonSession $lessonSession): JsonResponse
    {
        $behavior = $lessonSession->behaviorRecords()
            ->with('student')
            ->get();

        return response()->json([
            'data' => $behavior
        ]);
    }

    public function updateNote(Request $request, LessonSession $lessonSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lesson_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lessonSession->update([
                'lesson_note' => $request->lesson_note,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Lesson note updated successfully',
                'data' => $lessonSession->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update lesson note',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function updateTags(Request $request, LessonSession $lessonSession): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lesson_tags' => 'required|array|max:4',
            'lesson_tags.*' => 'string|in:new_topic,review,homework,test',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lessonSession->update([
                'lesson_tags' => $request->lesson_tags,
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'message' => 'Lesson tags updated successfully',
                'data' => $lessonSession->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update lesson tags',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}


<?php

namespace App\Http\Controllers\API\V1\Schedule;

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

        $lessons = $this->lessonService->getWithFilters($filters);

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

    /**
     * Get lesson attendance with summary
     */
    public function getAttendance(Lesson $lesson): JsonResponse
    {
        try {
            $attendances = $lesson->attendances()->with(['student'])->get();

            $students = $lesson->class->students()->wherePivot('status', 'active')->get();

            $attendanceMap = $attendances->keyBy('student_id');

            $studentsData = $students->map(function ($student) use ($attendanceMap) {
                $attendance = $attendanceMap->get($student->id);
                return [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'photo_url' => null, // TODO: Add photo URL when implemented
                    'status' => $attendance ? $attendance->status : 'absent',
                    'arrival_time' => $attendance?->arrival_time?->format('H:i'),
                    'notes' => $attendance?->notes,
                ];
            });

            $summary = [
                'present' => $attendances->whereIn('status', ['present', 'late', 'online_present'])->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'total' => $students->count(),
            ];

            return response()->json([
                'message' => 'Lesson attendance retrieved successfully',
                'data' => [
                    'lesson' => new LessonResource($lesson->load(['class', 'schedule'])),
                    'students' => $studentsData,
                    'summary' => $summary,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve lesson attendance',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Quick mark all present except specified students
     */
    public function quickMarkAll(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:present,absent',
            'except' => 'nullable|array',
            'except.*' => 'exists:students,id',
        ]);

        try {
            $students = $lesson->class->students()->wherePivot('status', 'active')->get();
            $exceptIds = $request->except ?? [];

            $attendanceData = [];
            foreach ($students as $student) {
                if (!in_array($student->id, $exceptIds)) {
                    $attendanceData[] = [
                        'student_id' => $student->id,
                        'status' => $request->status,
                    ];
                } else {
                    // Keep existing status for excepted students
                    $existing = $lesson->attendances()->where('student_id', $student->id)->first();
                    if ($existing) {
                        $attendanceData[] = [
                            'student_id' => $student->id,
                            'status' => $existing->status,
                        ];
                    }
                }
            }

            $attendances = $this->lessonService->markAttendance($lesson, $attendanceData);

            return response()->json([
                'message' => 'Quick mark completed successfully',
                'data' => [
                    'lesson' => new LessonResource($lesson->fresh()),
                    'marked_count' => count($attendanceData),
                    'excepted_count' => count($exceptIds),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to quick mark attendance',
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

    /**
     * Teacher Lessons - Get lessons for teacher portal
     */
    public function teacherLessons(Request $request): JsonResponse
    {
        // Verificar se o usuário é um professor
        $user = auth()->user();
        if (!$user->hasRole('teacher') && !$user->teacher) {
            return response()->json(['message' => 'Access denied. Teacher role required.'], 403);
        }

        $teacherId = $user->teacher->id;
        $filters = array_merge($request->only([
            'class_id', 'subject_id', 'type', 'status',
            'date_from', 'date_to', 'is_online',
            'search', 'sort_by', 'sort_direction', 'per_page'
        ]), ['teacher_id' => $teacherId]);

        $lessons = $this->lessonService->getWithFilters($filters);

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

    /**
     * Student Today Lessons - Get today's lessons for student
     */
    public function studentTodayLessons(): JsonResponse
    {
        // Verificar se o usuário é um estudante
        $user = auth()->user();
        if (!$user->hasRole('student') && !$user->student) {
            return response()->json(['message' => 'Access denied. Student role required.'], 403);
        }

        $student = $user->student;
        $lessons = Lesson::whereHas('class.students', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })->today()->with(['subject', 'teacher', 'contents'])->get();

        return response()->json([
            'data' => LessonResource::collection($lessons)
        ]);
    }

    /**
     * Student Upcoming Lessons - Get upcoming lessons for student
     */
    public function studentUpcomingLessons(): JsonResponse
    {
        // Verificar se o usuário é um estudante
        $user = auth()->user();
        if (!$user->hasRole('student') && !$user->student) {
            return response()->json(['message' => 'Access denied. Student role required.'], 403);
        }

        $student = $user->student;
        $lessons = Lesson::whereHas('class.students', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })->upcoming()->limit(10)->with(['subject', 'teacher', 'contents'])->get();

        return response()->json([
            'data' => LessonResource::collection($lessons)
        ]);
    }

    /**
     * Download Lesson Content - Download lesson content file
     */
    public function downloadContent($lessonContentId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $content = \App\Models\V1\Schedule\LessonContent::findOrFail($lessonContentId);

        // Verificar se o usuário tem acesso ao conteúdo
        $user = auth()->user();
        $hasAccess = false;

        // Verificar se é o professor da aula
        if ($user->hasRole('teacher') && $user->teacher && $content->lesson->teacher_id === $user->teacher->id) {
            $hasAccess = true;
        }

        // Verificar se é estudante da turma
        if ($user->hasRole('student') && $user->student) {
            $hasAccess = $content->lesson->class->students()->where('student_id', $user->student->id)->exists();
        }

        // Verificar se é pai/mãe de algum estudante da turma
        if ($user->hasRole('parent')) {
            $childrenIds = $user->students->pluck('id');
            $hasAccess = $content->lesson->class->students()->whereIn('student_id', $childrenIds)->exists();
        }

        // Verificar se é admin ou coordenador
        if ($user->hasAnyRole(['admin', 'coordinator'])) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            abort(403, 'Access denied to this content');
        }

        // Validate school ownership
        $user = auth()->user();
        $userSchool = $user->activeSchools()->first();
        if (!$userSchool || $content->school_id !== $userSchool->id) {
            abort(403, 'Content does not belong to current school');
        }

        if (!$content->isDownloadable()) {
            abort(403, 'Content is not downloadable');
        }

        return \Illuminate\Support\Facades\Storage::download($content->file_path, $content->file_name);
    }
}

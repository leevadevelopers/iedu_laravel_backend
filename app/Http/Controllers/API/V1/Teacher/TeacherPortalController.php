<?php

namespace App\Http\Controllers\API\V1\Teacher;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\Student\Student;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherPortalController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get teacher dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $teacher = Teacher::where('user_id', $user->id)->first();

            if (!$teacher) {
                return $this->errorResponse('Teacher profile not found', 404);
            }

            $schoolId = $this->getCurrentSchoolId();

            // Today's schedule
            $todayLessons = Lesson::where('teacher_id', $teacher->id)
                ->where('school_id', $schoolId)
                ->whereDate('lesson_date', today())
                ->with(['class', 'schedule.subject', 'subject'])
                ->orderBy('start_time')
                ->get();

            $todaySchedule = $todayLessons->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'time' => $lesson->start_time?->format('H:i'),
                    'subject' => $lesson->schedule?->subject->name ?? $lesson->subject->name ?? 'N/A',
                    'class' => $lesson->class->name ?? 'N/A',
                    'room' => $lesson->classroom ?? 'N/A',
                    'status' => $lesson->status,
                ];
            });

            // My classes
            $myClasses = AcademicClass::where('primary_teacher_id', $teacher->id)
                ->where('school_id', $schoolId)
                ->with(['subject', 'students'])
                ->get();

            $classesSummary = $myClasses->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'subject' => $class->subject->name ?? 'N/A',
                    'grade_level' => $class->grade_level,
                    'enrollment' => $class->current_enrollment,
                    'max_students' => $class->max_students,
                ];
            });

            // Pending tasks
            $pendingGrades = GradeEntry::where('entered_by', $teacher->id)
                ->whereNull('percentage_score')
                ->whereNull('raw_score')
                ->count();

            $pendingAttendance = LessonAttendance::whereHas('lesson', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id)
                    ->whereDate('lesson_date', today());
            })
            ->whereNull('status')
            ->count();

            // Recent grades entered
            $recentGrades = GradeEntry::where('entered_by', $teacher->id)
                ->with(['student', 'class.subject'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($entry) {
                    return [
                        'student' => $entry->student->first_name . ' ' . $entry->student->last_name,
                        'subject' => $entry->class->subject->name ?? 'N/A',
                        'grade' => $entry->percentage_score ?? $entry->raw_score,
                        'date' => $entry->assessment_date ? \Carbon\Carbon::parse($entry->assessment_date)->toIso8601String() : null,
                    ];
                });

            // Statistics
            $totalStudents = $myClasses->sum(function ($class) {
                return $class->students->count();
            });

            $thisWeekLessons = Lesson::where('teacher_id', $teacher->id)
                ->where('school_id', $schoolId)
                ->whereBetween('lesson_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            return $this->successResponse([
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->full_name,
                ],
                'today_schedule' => $todaySchedule,
                'my_classes' => $classesSummary,
                'pending_tasks' => [
                    'grades' => $pendingGrades,
                    'attendance' => $pendingAttendance,
                ],
                'recent_grades' => $recentGrades,
                'statistics' => [
                    'total_students' => $totalStudents,
                    'total_classes' => $myClasses->count(),
                    'this_week_lessons' => $thisWeekLessons,
                ],
            ], 'Teacher dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get my classes
     */
    public function myClasses(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $teacher = Teacher::where('user_id', $user->id)->firstOrFail();

            $schoolId = $this->getCurrentSchoolId();

            $classes = AcademicClass::where('primary_teacher_id', $teacher->id)
                ->where('school_id', $schoolId)
                ->with(['subject', 'students', 'academicYear', 'academicTerm'])
                ->get();

            $classesData = $classes->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'subject' => [
                        'id' => $class->subject->id ?? null,
                        'name' => $class->subject->name ?? 'N/A',
                    ],
                    'grade_level' => $class->grade_level,
                    'enrollment' => [
                        'current' => $class->current_enrollment,
                        'max' => $class->max_students,
                    ],
                    'academic_year' => $class->academicYear->name ?? null,
                    'academic_term' => $class->academicTerm->name ?? null,
                    'students_count' => $class->students->count(),
                ];
            });

            return $this->successResponse(
                $classesData,
                'My classes retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve classes: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get class students
     */
    public function classStudents(Request $request, int $classId): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $teacher = Teacher::where('user_id', $user->id)->firstOrFail();

            $class = AcademicClass::where('id', $classId)
                ->where('primary_teacher_id', $teacher->id)
                ->with(['students'])
                ->firstOrFail();

            $students = $class->students()->wherePivot('status', 'active')
                ->with(['currentAcademicYear'])
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->first_name . ' ' . $student->last_name,
                        'student_number' => $student->student_number,
                        'grade_level' => $student->current_grade_level,
                        'attendance_rate' => $student->attendance_rate,
                        'current_gpa' => $student->current_gpa,
                    ];
                });

            return $this->successResponse([
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                ],
                'students' => $students,
            ], 'Class students retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve class students: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get my schedule
     */
    public function mySchedule(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $teacher = Teacher::where('user_id', $user->id)->firstOrFail();

            $schoolId = $this->getCurrentSchoolId();
            $from = $request->get('from', now()->startOfWeek()->toDateString());
            $to = $request->get('to', now()->endOfWeek()->toDateString());

            $lessons = Lesson::where('teacher_id', $teacher->id)
                ->where('school_id', $schoolId)
                ->whereBetween('lesson_date', [$from, $to])
                ->with(['class', 'schedule.subject', 'subject'])
                ->orderBy('lesson_date')
                ->orderBy('start_time')
                ->get();

            $schedule = $lessons->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'date' => $lesson->lesson_date->toIso8601String(),
                    'time' => $lesson->start_time?->format('H:i') . ' - ' . $lesson->end_time?->format('H:i'),
                    'subject' => $lesson->schedule?->subject->name ?? $lesson->subject->name ?? 'N/A',
                    'class' => $lesson->class->name ?? 'N/A',
                    'room' => $lesson->classroom ?? 'N/A',
                    'status' => $lesson->status,
                ];
            });

            return $this->successResponse([
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'schedule' => $schedule,
            ], 'Schedule retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve schedule: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}


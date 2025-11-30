<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\Student\Student;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Bulk mark attendance for a class
     */
    public function bulkMark(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'date' => 'required|date',
            'lesson_id' => 'nullable|exists:lessons,id',
            'attendances' => 'required|array|min:1',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status' => 'required|in:present,absent,late,excused,left_early,partial,online_present',
            'attendances.*.notes' => 'nullable|string|max:255',
            'attendances.*.arrival_time' => 'nullable|date_format:H:i',
        ]);

        try {
            $schoolId = $this->getCurrentSchoolId();
            $class = AcademicClass::findOrFail($request->class_id);

            // Verify class belongs to school
            if ($class->school_id !== $schoolId) {
                return $this->errorResponse('Class does not belong to your school', 403);
            }

            DB::beginTransaction();

            $lesson = null;
            if ($request->lesson_id) {
                $lesson = Lesson::findOrFail($request->lesson_id);
            } else {
                // Create or find lesson for this class and date
                $lesson = Lesson::firstOrCreate([
                    'class_id' => $class->id,
                    'lesson_date' => $request->date,
                    'school_id' => $schoolId,
                ], [
                    'status' => 'completed',
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                ]);
            }

            $marked = [];
            $errors = [];

            foreach ($request->attendances as $attendanceData) {
                try {
                    $attendance = LessonAttendance::updateOrCreate(
                        [
                            'lesson_id' => $lesson->id,
                            'student_id' => $attendanceData['student_id'],
                        ],
                        [
                            'school_id' => $schoolId,
                            'status' => $attendanceData['status'],
                            'notes' => $attendanceData['notes'] ?? null,
                            'arrival_time' => $attendanceData['arrival_time'] ?? null,
                            'marked_by' => auth('api')->id(),
                            'marked_by_method' => 'bulk',
                        ]
                    );

                    $marked[] = $attendance;

                    // Send absence notification if absent
                    if ($attendanceData['status'] === 'absent') {
                        // TODO: Send SMS notification to parent
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $attendanceData['student_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Update lesson attendance stats
            if ($lesson) {
                $lesson->updateAttendanceStats();
            }

            DB::commit();

            return $this->successResponse([
                'marked_count' => count($marked),
                'error_count' => count($errors),
                'errors' => $errors,
                'lesson' => $lesson,
            ], 'Bulk attendance marked successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to mark bulk attendance: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get class attendance summary
     */
    public function classSummary(Request $request, int $classId): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        try {
            $schoolId = $this->getCurrentSchoolId();
            $class = AcademicClass::findOrFail($classId);

            // Verify class belongs to school
            if ($class->school_id !== $schoolId) {
                return $this->errorResponse('Class does not belong to your school', 403);
            }

            // Get all lessons for this class in date range
            $lessons = Lesson::where('class_id', $classId)
                ->where('school_id', $schoolId)
                ->whereBetween('lesson_date', [$request->from, $request->to])
                ->with('attendances')
                ->get();

            // Get all students in class
            $students = $class->students()->wherePivot('status', 'active')->get();

            $summary = [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'grade_level' => $class->grade_level,
                ],
                'period' => [
                    'from' => $request->from,
                    'to' => $request->to,
                ],
                'total_lessons' => $lessons->count(),
                'students' => $students->map(function ($student) use ($lessons) {
                    $studentAttendances = $lessons->flatMap(function ($lesson) use ($student) {
                        return $lesson->attendances->where('student_id', $student->id);
                    });

                    $total = $studentAttendances->count();
                    $present = $studentAttendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $absent = $studentAttendances->where('status', 'absent')->count();
                    $late = $studentAttendances->where('status', 'late')->count();
                    $excused = $studentAttendances->where('status', 'excused')->count();

                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    return [
                        'student_id' => $student->id,
                        'name' => $student->first_name . ' ' . $student->last_name,
                        'total' => $total,
                        'present' => $present,
                        'absent' => $absent,
                        'late' => $late,
                        'excused' => $excused,
                        'attendance_percentage' => $percentage,
                    ];
                }),
                'overall' => [
                    'total_students' => $students->count(),
                    'average_attendance' => $students->count() > 0
                        ? round($students->map(function ($student) use ($lessons) {
                            $studentAttendances = $lessons->flatMap(function ($lesson) use ($student) {
                                return $lesson->attendances->where('student_id', $student->id);
                            });
                            $total = $studentAttendances->count();
                            $present = $studentAttendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                            return $total > 0 ? ($present / $total) * 100 : 0;
                        })->avg(), 2)
                        : 0,
                ],
            ];

            return $this->successResponse($summary, 'Class attendance summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve class attendance summary: ' . $e->getMessage(),
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


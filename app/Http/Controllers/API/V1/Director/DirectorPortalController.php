<?php

namespace App\Http\Controllers\API\V1\Director;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\Student\Student;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorPortalController extends BaseController
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get director dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            if (!$schoolId) {
                return $this->errorResponse('School context required', 403);
            }

            // School statistics
            $totalStudents = Student::where('school_id', $schoolId)
                ->where('enrollment_status', 'enrolled')
                ->count();

            $totalTeachers = Teacher::where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();

            $totalClasses = AcademicClass::where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();

            // Financial statistics
            $totalRevenue = Payment::where('school_id', $schoolId)
                ->where('status', 'completed')
                ->whereMonth('paid_at', now()->month)
                ->sum('amount');

            $pendingFees = Invoice::where('school_id', $schoolId)
                ->where('status', '!=', 'paid')
                ->sum(DB::raw('total - COALESCE((SELECT SUM(amount) FROM payments WHERE payments.invoice_id = invoices.id AND payments.status = "completed"), 0)'));

            // Attendance statistics
            $todayLessons = Lesson::where('school_id', $schoolId)
                ->whereDate('lesson_date', today())
                ->count();

            $todayAttendance = LessonAttendance::whereHas('lesson', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId)
                    ->whereDate('lesson_date', today());
            })
            ->whereIn('status', ['present', 'late', 'online_present'])
            ->count();

            $todayExpected = LessonAttendance::whereHas('lesson', function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId)
                    ->whereDate('lesson_date', today());
            })
            ->count();

            $attendanceRate = $todayExpected > 0 ? round(($todayAttendance / $todayExpected) * 100, 2) : 0;

            // Recent activities
            $recentEnrollments = Student::where('school_id', $schoolId)
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->count();

            $recentPayments = Payment::where('school_id', $schoolId)
                ->whereDate('paid_at', '>=', now()->subDays(7))
                ->count();

            // Alerts
            $alerts = $this->getAlerts($schoolId);

            return $this->successResponse([
                'school' => [
                    'id' => $schoolId,
                    'name' => $this->getSchoolName($schoolId),
                ],
                'statistics' => [
                    'students' => [
                        'total' => $totalStudents,
                        'recent_enrollments' => $recentEnrollments,
                    ],
                    'teachers' => [
                        'total' => $totalTeachers,
                    ],
                    'classes' => [
                        'total' => $totalClasses,
                    ],
                    'financial' => [
                        'monthly_revenue' => (float) $totalRevenue,
                        'pending_fees' => (float) $pendingFees,
                        'recent_payments' => $recentPayments,
                    ],
                    'attendance' => [
                        'today_lessons' => $todayLessons,
                        'today_attendance_rate' => $attendanceRate,
                        'today_present' => $todayAttendance,
                        'today_expected' => $todayExpected,
                    ],
                ],
                'alerts' => $alerts,
            ], 'Director dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get school statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();
            $period = $request->get('period', 'month'); // month, quarter, year

            $from = match ($period) {
                'month' => now()->startOfMonth(),
                'quarter' => now()->startOfQuarter(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };

            // Student statistics
            $studentStats = [
                'total' => Student::where('school_id', $schoolId)->count(),
                'enrolled' => Student::where('school_id', $schoolId)->where('enrollment_status', 'enrolled')->count(),
                'new_this_period' => Student::where('school_id', $schoolId)
                    ->where('created_at', '>=', $from)
                    ->count(),
            ];

            // Attendance statistics
            $attendanceStats = $this->getAttendanceStatistics($schoolId, $from);

            // Financial statistics
            $financialStats = [
                'total_revenue' => (float) Payment::where('school_id', $schoolId)
                    ->where('status', 'completed')
                    ->where('paid_at', '>=', $from)
                    ->sum('amount'),
                'total_invoices' => Invoice::where('school_id', $schoolId)
                    ->where('created_at', '>=', $from)
                    ->count(),
                'paid_invoices' => Invoice::where('school_id', $schoolId)
                    ->where('status', 'paid')
                    ->where('paid_at', '>=', $from)
                    ->count(),
            ];

            // Academic statistics
            $academicStats = [
                'total_grades_entered' => GradeEntry::where('school_id', $schoolId)
                    ->where('created_at', '>=', $from)
                    ->count(),
                'average_gpa' => (float) Student::where('school_id', $schoolId)
                    ->whereNotNull('current_gpa')
                    ->avg('current_gpa'),
            ];

            return $this->successResponse([
                'period' => $period,
                'from' => $from->toIso8601String(),
                'to' => now()->toIso8601String(),
                'students' => $studentStats,
                'attendance' => $attendanceStats,
                'financial' => $financialStats,
                'academic' => $academicStats,
            ], 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve statistics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get attendance statistics
     */
    protected function getAttendanceStatistics(int $schoolId, $from): array
    {
        $lessons = Lesson::where('school_id', $schoolId)
            ->where('lesson_date', '>=', $from)
            ->get();

        $totalLessons = $lessons->count();
        $totalAttendances = LessonAttendance::whereHas('lesson', function ($query) use ($schoolId, $from) {
            $query->where('school_id', $schoolId)
                ->where('lesson_date', '>=', $from);
        })->count();

        $presentCount = LessonAttendance::whereHas('lesson', function ($query) use ($schoolId, $from) {
            $query->where('school_id', $schoolId)
                ->where('lesson_date', '>=', $from);
        })
        ->whereIn('status', ['present', 'late', 'online_present'])
        ->count();

        $absentCount = LessonAttendance::whereHas('lesson', function ($query) use ($schoolId, $from) {
            $query->where('school_id', $schoolId)
                ->where('lesson_date', '>=', $from);
        })
        ->where('status', 'absent')
        ->count();

        return [
            'total_lessons' => $totalLessons,
            'total_attendances' => $totalAttendances,
            'present' => $presentCount,
            'absent' => $absentCount,
            'attendance_rate' => $totalAttendances > 0 ? round(($presentCount / $totalAttendances) * 100, 2) : 0,
        ];
    }

    /**
     * Get alerts
     */
    protected function getAlerts(int $schoolId): array
    {
        $alerts = [];

        // Low attendance alerts
        $lowAttendanceStudents = Student::where('school_id', $schoolId)
            ->where('attendance_rate', '<', 80)
            ->whereNotNull('attendance_rate')
            ->count();

        if ($lowAttendanceStudents > 0) {
            $alerts[] = [
                'type' => 'attendance',
                'message' => "{$lowAttendanceStudents} students with low attendance (< 80%)",
                'priority' => 'medium',
            ];
        }

        // Overdue payments
        $overdueInvoices = Invoice::where('school_id', $schoolId)
            ->where('status', '!=', 'paid')
            ->where('due_at', '<', now())
            ->count();

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'type' => 'financial',
                'message' => "{$overdueInvoices} invoices overdue",
                'priority' => 'high',
            ];
        }

        // Classes at capacity
        $fullClasses = AcademicClass::where('school_id', $schoolId)
            ->whereRaw('current_enrollment >= max_students')
            ->count();

        if ($fullClasses > 0) {
            $alerts[] = [
                'type' => 'capacity',
                'message' => "{$fullClasses} classes at full capacity",
                'priority' => 'low',
            ];
        }

        return $alerts;
    }

    /**
     * Get school name
     */
    protected function getSchoolName(int $schoolId): string
    {
        $school = \App\Models\V1\SIS\School\School::find($schoolId);
        return $school ? ($school->official_name ?? $school->name ?? 'School') : 'School';
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


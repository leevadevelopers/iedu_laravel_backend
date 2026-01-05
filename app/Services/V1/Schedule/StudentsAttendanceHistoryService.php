<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\LessonSession;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Academic\AcademicClass;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StudentsAttendanceHistoryService
{
    /**
     * Get students history with aggregated statistics
     */
    public function getStudentsHistory(array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $tenantId = $filters['tenant_id'] ?? null;
        $classId = $filters['class_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        // Build query for lesson sessions in date range
        $sessionsQuery = LessonSession::query()
            ->where('status', 'completed')
            ->where('school_id', $schoolId);

        if ($tenantId) {
            $sessionsQuery->where('tenant_id', $tenantId);
        }

        if ($classId) {
            $sessionsQuery->where('class_id', $classId);
        }

        if ($dateFrom) {
            $sessionsQuery->whereDate('started_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $sessionsQuery->whereDate('started_at', '<=', $dateTo);
        }

        $sessionIds = $sessionsQuery->pluck('id');

        // Get all students (from class if specified, or all students in school)
        if ($classId) {
            $class = AcademicClass::find($classId);
            $students = $class ? $class->students()->wherePivot('status', 'active')->get() : collect();
        } else {
            $studentsQuery = Student::query()->where('school_id', $schoolId);
            if ($tenantId) {
                $studentsQuery->where('tenant_id', $tenantId);
            }
            $students = $studentsQuery->get();
        }

        // Calculate statistics for each student
        $results = [];
        foreach ($students as $student) {
            $attendances = LessonAttendance::whereIn('lesson_session_id', $sessionIds)
                ->where('student_id', $student->id)
                ->get();

            $total = $attendances->count();
            $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
            $absent = $attendances->where('status', 'absent')->count();
            $late = $attendances->where('status', 'late')->count();
            $excused = $attendances->where('status', 'excused')->count();

            $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

            $results[] = [
                'student_id' => $student->id,
                'student' => [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => $student->first_name . ' ' . $student->last_name,
                    'student_number' => $student->student_number,
                ],
                'class' => $classId ? [
                    'id' => $class->id ?? null,
                    'name' => $class->name ?? null,
                ] : null,
                'total_lessons' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'attendance_percentage' => $percentage,
                'status' => $this->getAttendanceStatus($percentage),
            ];
        }

        return $results;
    }

    /**
     * Get detailed information about a specific student's attendance
     */
    public function getStudentDetail(int $studentId, array $filters): array
    {
        $schoolId = $filters['school_id'] ?? null;
        $tenantId = $filters['tenant_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? Carbon::now()->startOfYear()->toDateString();
        $dateTo = $filters['date_to'] ?? Carbon::now()->toDateString();

        $student = Student::find($studentId);
        if (!$student) {
            throw new \Exception('Student not found');
        }

        // Get all lesson sessions for this student in date range
        $sessionsQuery = LessonSession::query()
            ->where('status', 'completed')
            ->where('school_id', $schoolId)
            ->whereDate('started_at', '>=', $dateFrom)
            ->whereDate('started_at', '<=', $dateTo);

        if ($tenantId) {
            $sessionsQuery->where('tenant_id', $tenantId);
        }

        $sessions = $sessionsQuery->with([
            'teacher',
            'subject',
            'class',
            'attendanceRecords' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }
        ])->get();

        // Calculate overall statistics
        $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions->pluck('id'))
            ->where('student_id', $studentId)
            ->get();

        $total = $attendances->count();
        $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
        $absent = $attendances->where('status', 'absent')->count();
        $late = $attendances->where('status', 'late')->count();
        $excused = $attendances->where('status', 'excused')->count();

        $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

        // Get statistics by subject
        $bySubject = $this->calculateBySubject($sessions, $studentId);

        return [
            'student' => [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->first_name . ' ' . $student->last_name,
                'student_number' => $student->student_number,
                'photo' => $student->photo ?? null,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'overall_statistics' => [
                'total_lessons' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'attendance_percentage' => $percentage,
                'status' => $this->getAttendanceStatus($percentage),
            ],
            'by_subject' => $bySubject,
            'lessons' => $sessions->map(function ($session) use ($studentId) {
                $attendance = $session->attendanceRecords->first();
                return [
                    'id' => $session->id,
                    'date' => $session->started_at->format('Y-m-d'),
                    'time' => $session->started_at->format('H:i'),
                    'subject' => $session->subject ? [
                        'id' => $session->subject->id,
                        'name' => $session->subject->name,
                    ] : null,
                    'teacher' => $session->teacher ? [
                        'id' => $session->teacher->id,
                        'name' => $session->teacher->full_name ?? $session->teacher->first_name . ' ' . $session->teacher->last_name,
                    ] : null,
                    'class' => $session->class ? [
                        'id' => $session->class->id,
                        'name' => $session->class->name,
                    ] : null,
                    'status' => $attendance ? $attendance->status : null,
                    'notes' => $attendance ? $attendance->notes : null,
                ];
            }),
        ];
    }

    /**
     * Calculate statistics by period type
     */
    public function calculatePeriodStats(int $studentId, string $periodType, Carbon $start, Carbon $end): array
    {
        $stats = [];

        switch ($periodType) {
            case 'day':
                $current = $start->copy();
                while ($current->lte($end)) {
                    $dayStart = $current->copy()->startOfDay();
                    $dayEnd = $current->copy()->endOfDay();
                    
                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$dayStart, $dayEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $current->format('Y-m-d'),
                        'label' => $current->format('d/m/Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current->addDay();
                }
                break;

            case 'week':
                $current = $start->copy()->startOfWeek();
                while ($current->lte($end)) {
                    $weekStart = $current->copy();
                    $weekEnd = $current->copy()->endOfWeek();

                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$weekStart, $weekEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $weekStart->format('Y-m-d'),
                        'label' => 'Semana ' . $weekStart->format('d/m') . ' - ' . $weekEnd->format('d/m/Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current->addWeek();
                }
                break;

            case 'month':
                $current = $start->copy()->startOfMonth();
                while ($current->lte($end)) {
                    $monthStart = $current->copy();
                    $monthEnd = $current->copy()->endOfMonth();

                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$monthStart, $monthEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $monthStart->format('Y-m'),
                        'label' => $monthStart->format('F Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current->addMonth();
                }
                break;

            case 'quarter':
                $current = $start->copy();
                while ($current->lte($end)) {
                    $quarter = ceil($current->month / 3);
                    $quarterStart = $current->copy()->month(($quarter - 1) * 3 + 1)->startOfMonth();
                    $quarterEnd = $quarterStart->copy()->addMonths(2)->endOfMonth();

                    if ($quarterStart->lt($start)) {
                        $quarterStart = $start->copy();
                    }
                    if ($quarterEnd->gt($end)) {
                        $quarterEnd = $end->copy();
                    }

                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$quarterStart, $quarterEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $quarterStart->format('Y') . '-Q' . $quarter,
                        'label' => $quarterStart->format('M') . ' - ' . $quarterEnd->format('M Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current = $quarterEnd->copy()->addDay();
                }
                break;

            case 'semester':
                $current = $start->copy();
                while ($current->lte($end)) {
                    $semester = $current->month <= 6 ? 1 : 2;
                    $semesterStart = $current->copy()->month($semester === 1 ? 1 : 7)->startOfMonth();
                    $semesterEnd = $semesterStart->copy()->addMonths(5)->endOfMonth();

                    if ($semesterStart->lt($start)) {
                        $semesterStart = $start->copy();
                    }
                    if ($semesterEnd->gt($end)) {
                        $semesterEnd = $end->copy();
                    }

                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$semesterStart, $semesterEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $semesterStart->format('Y') . '-S' . $semester,
                        'label' => $semesterStart->format('M') . ' - ' . $semesterEnd->format('M Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current = $semesterEnd->copy()->addDay();
                }
                break;

            case 'year':
                $current = $start->copy()->startOfYear();
                while ($current->lte($end)) {
                    $yearStart = $current->copy();
                    $yearEnd = $current->copy()->endOfYear();

                    if ($yearStart->lt($start)) {
                        $yearStart = $start->copy();
                    }
                    if ($yearEnd->gt($end)) {
                        $yearEnd = $end->copy();
                    }

                    $sessions = LessonSession::where('status', 'completed')
                        ->whereBetween('started_at', [$yearStart, $yearEnd])
                        ->pluck('id');

                    $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
                        ->where('student_id', $studentId)
                        ->get();

                    $total = $attendances->count();
                    $present = $attendances->whereIn('status', ['present', 'late', 'online_present'])->count();
                    $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                    $stats[] = [
                        'period' => $yearStart->format('Y'),
                        'label' => $yearStart->format('Y'),
                        'total' => $total,
                        'present' => $present,
                        'absent' => $total - $present,
                        'percentage' => $percentage,
                    ];

                    $current->addYear();
                }
                break;
        }

        return $stats;
    }

    /**
     * Get calendar data for attendance visualization
     */
    public function getCalendarData(int $studentId, Carbon $start, Carbon $end): array
    {
        $sessions = LessonSession::where('status', 'completed')
            ->whereBetween('started_at', [$start, $end])
            ->pluck('id');

        $attendances = LessonAttendance::whereIn('lesson_session_id', $sessions)
            ->where('student_id', $studentId)
            ->with('lessonSession')
            ->get();

        $calendarData = [];
        foreach ($attendances as $attendance) {
            $date = $attendance->lessonSession->started_at->format('Y-m-d');
            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [
                    'date' => $date,
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'excused' => 0,
                ];
            }

            $calendarData[$date]['total']++;
            if (in_array($attendance->status, ['present', 'late', 'online_present'])) {
                $calendarData[$date]['present']++;
            } elseif ($attendance->status === 'absent') {
                $calendarData[$date]['absent']++;
            } elseif ($attendance->status === 'late') {
                $calendarData[$date]['late']++;
            } elseif ($attendance->status === 'excused') {
                $calendarData[$date]['excused']++;
            }
        }

        return array_values($calendarData);
    }

    /**
     * Get trend analysis
     */
    public function getTrendAnalysis(int $studentId, Carbon $start, Carbon $end): array
    {
        // Calculate monthly trends
        $monthlyStats = $this->calculatePeriodStats($studentId, 'month', $start, $end);
        
        if (count($monthlyStats) < 2) {
            return [
                'trend' => 'stable',
                'message' => 'Insufficient data for trend analysis',
            ];
        }

        $recent = array_slice($monthlyStats, -3);
        $older = array_slice($monthlyStats, 0, -3);

        $recentAvg = count($recent) > 0 
            ? array_sum(array_column($recent, 'percentage')) / count($recent) 
            : 0;
        
        $olderAvg = count($older) > 0 
            ? array_sum(array_column($older, 'percentage')) / count($older) 
            : $recentAvg;

        $difference = $recentAvg - $olderAvg;

        if ($difference > 5) {
            $trend = 'improving';
        } elseif ($difference < -5) {
            $trend = 'declining';
        } else {
            $trend = 'stable';
        }

        return [
            'trend' => $trend,
            'recent_average' => round($recentAvg, 2),
            'older_average' => round($olderAvg, 2),
            'difference' => round($difference, 2),
            'message' => $this->getTrendMessage($trend, $difference),
        ];
    }

    /**
     * Calculate statistics by subject
     */
    private function calculateBySubject($sessions, int $studentId): array
    {
        $bySubject = [];

        foreach ($sessions as $session) {
            $subjectId = $session->subject_id;
            $subjectName = $session->subject ? $session->subject->name : 'Unknown';

            if (!isset($bySubject[$subjectId])) {
                $bySubject[$subjectId] = [
                    'subject_id' => $subjectId,
                    'subject_name' => $subjectName,
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                ];
            }

            $attendance = $session->attendanceRecords->first();
            if ($attendance) {
                $bySubject[$subjectId]['total']++;
                if (in_array($attendance->status, ['present', 'late', 'online_present'])) {
                    $bySubject[$subjectId]['present']++;
                } elseif ($attendance->status === 'absent') {
                    $bySubject[$subjectId]['absent']++;
                } elseif ($attendance->status === 'late') {
                    $bySubject[$subjectId]['late']++;
                }
            }
        }

        // Calculate percentages
        foreach ($bySubject as &$subject) {
            $subject['percentage'] = $subject['total'] > 0 
                ? round(($subject['present'] / $subject['total']) * 100, 2) 
                : 0;
        }

        return array_values($bySubject);
    }

    /**
     * Get attendance status based on percentage
     */
    private function getAttendanceStatus(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'excellent';
        } elseif ($percentage >= 75) {
            return 'good';
        } elseif ($percentage >= 60) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    /**
     * Get trend message
     */
    private function getTrendMessage(string $trend, float $difference): string
    {
        switch ($trend) {
            case 'improving':
                return sprintf('Presença melhorando em %.1f%%', abs($difference));
            case 'declining':
                return sprintf('Presença piorando em %.1f%%', abs($difference));
            default:
                return 'Presença estável';
        }
    }
}


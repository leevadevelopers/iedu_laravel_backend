<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Resources\Student\StudentDashboardResource;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPortalController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get student dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Get student associated with user
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                return $this->errorResponse('Student profile not found', 404);
            }

            // Get today's lessons
            $todayLessons = \App\Models\V1\Schedule\Lesson::where('class_id', function ($query) use ($student) {
                $query->select('class_id')
                    ->from('student_class_enrollments')
                    ->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->limit(1);
            })
            ->where('lesson_date', now()->toDateString())
            ->with(['schedule.subject', 'schedule.teacher'])
            ->orderBy('start_time')
            ->get();

            $todayData = [
                'date' => now()->toDateString(),
                'lessons' => $todayLessons->map(function ($lesson) {
                    return [
                        'time' => $lesson->start_time?->format('H:i'),
                        'subject' => $lesson->schedule->subject->name ?? 'N/A',
                        'teacher' => $lesson->schedule->teacher->name ?? 'N/A',
                        'room' => $lesson->classroom ?? 'N/A',
                    ];
                }),
                'homework_due' => 0, // TODO: Implement homework tracking
            ];

            // Get summary
            $attendancePercentage = $this->calculateAttendancePercentage($student->id);
            $averageGrade = $this->calculateAverageGrade($student->id);
            $feesBalance = $this->getFeesBalance($student->id);

            $summary = [
                'attendance_percentage' => $attendancePercentage,
                'average_grade' => $averageGrade,
                'fees_balance' => $feesBalance,
            ];

            // Get recent grades
            $recentGrades = $this->getRecentGrades($student->id, 5);

            // Get upcoming events (placeholder)
            $upcomingEvents = [];

            return $this->successResponse([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'class' => $student->current_grade_level,
                ],
                'today' => $todayData,
                'summary' => $summary,
                'recent_grades' => $recentGrades,
                'upcoming_events' => $upcomingEvents,
            ], 'Student dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get student's own grades
     */
    public function myGrades(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            // Get all grades
            $gradeEntries = GradeEntry::where('student_id', $student->id)
                ->with(['class', 'academicTerm', 'class.subject', 'enteredBy'])
                ->orderBy('assessment_date', 'desc')
                ->get();

            // Group by subject
            $subjects = $gradeEntries->groupBy(function ($entry) {
                return $entry->class->subject->name ?? 'Unknown';
            })->map(function ($entries, $subjectName) use ($student) {
                $grades = $entries->map(function ($entry) {
                    return [
                        'name' => $entry->assessment_name,
                        'grade' => $entry->percentage_score ?? $entry->raw_score,
                        'date' => $entry->assessment_date->toIso8601String(),
                        'type' => $entry->assessment_type,
                        'teacher_comments' => $entry->teacher_comments,
                    ];
                });

                $currentGrade = $entries->avg('percentage_score') ?? $entries->avg('raw_score');
                $teacher = $entries->first()->enteredBy ?? $entries->first()->class->teacher ?? null;

                // Calculate rank
                $classId = $entries->first()->class_id ?? null;
                $myRank = $this->calculateRank($student->id, $classId, $currentGrade);

                return [
                    'name' => $subjectName,
                    'current_grade' => round($currentGrade, 2),
                    'trend' => $this->calculateTrend($entries),
                    'assessments' => $grades,
                    'class_average' => $this->calculateClassAverage($classId),
                    'my_rank' => $myRank,
                ];
            })->values();

            $overallAverage = $gradeEntries->avg('percentage_score') ?? $gradeEntries->avg('raw_score');

            return $this->successResponse([
                'overall_average' => round($overallAverage, 2),
                'subjects' => $subjects,
            ], 'My grades retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve grades: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get subject-specific grades
     */
    public function subjectGrades(Request $request, int $subjectId): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $gradeEntries = GradeEntry::where('student_id', $student->id)
                ->whereHas('class', function ($query) use ($subjectId) {
                    $query->where('subject_id', $subjectId);
                })
                ->with(['class', 'academicTerm', 'enteredBy'])
                ->orderBy('assessment_date', 'desc')
                ->get();

            return $this->successResponse([
                'subject_id' => $subjectId,
                'assessments' => $gradeEntries->map(function ($entry) {
                    return [
                        'name' => $entry->assessment_name,
                        'grade' => $entry->percentage_score ?? $entry->raw_score,
                        'date' => $entry->assessment_date->toIso8601String(),
                        'type' => $entry->assessment_type,
                        'teacher_comments' => $entry->teacher_comments,
                    ];
                }),
            ], 'Subject grades retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve subject grades: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get student's own attendance
     */
    public function myAttendance(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $from = $request->get('from', now()->subMonth()->toDateString());
            $to = $request->get('to', now()->toDateString());

            $attendances = LessonAttendance::where('student_id', $student->id)
                ->whereHas('lesson', function ($query) use ($from, $to) {
                    $query->whereBetween('lesson_date', [$from, $to]);
                })
                ->with(['lesson.schedule.subject', 'lesson.schedule.teacher'])
                ->orderBy('created_at', 'desc')
                ->get();

            $attendancePercentage = $this->calculateAttendancePercentage($student->id, $from, $to);

            $summary = [
                'total_lessons' => $attendances->count(),
                'present' => $attendances->whereIn('status', ['present', 'late', 'online_present'])->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'attendance_percentage' => $attendancePercentage,
            ];

            return $this->successResponse([
                'period' => [
                    'from' => $from,
                    'to' => $to,
                ],
                'summary' => $summary,
                'attendances' => $attendances->map(function ($attendance) {
                    return [
                        'date' => $attendance->lesson->lesson_date->toIso8601String(),
                        'subject' => $attendance->lesson->schedule->subject->name ?? 'N/A',
                        'teacher' => $attendance->lesson->schedule->teacher->name ?? 'N/A',
                        'status' => $attendance->status,
                        'arrival_time' => $attendance->arrival_time?->format('H:i'),
                        'notes' => $attendance->notes,
                    ];
                }),
            ], 'My attendance retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve attendance: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get student's own fees
     */
    public function myFees(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            // Get invoices for this student
            $invoices = Invoice::where('billable_type', Student::class)
                ->where('billable_id', $student->id)
                ->with(['items', 'payments'])
                ->orderBy('due_at', 'desc')
                ->get();

            $balance = $invoices->sum(function ($invoice) {
                return $invoice->getRemainingBalance();
            });

            $breakdown = $invoices->map(function ($invoice) {
                return [
                    'description' => $invoice->items->first()->description ?? 'Invoice ' . $invoice->reference,
                    'amount' => (float) $invoice->total,
                    'due_date' => $invoice->due_at?->toIso8601String(),
                    'status' => $invoice->status,
                    'remaining_balance' => (float) $invoice->getRemainingBalance(),
                ];
            });

            $paymentMethods = [
                ['type' => 'mpesa', 'enabled' => true],
                ['type' => 'emola', 'enabled' => true],
            ];

            $history = $invoices->flatMap(function ($invoice) {
                return $invoice->payments->map(function ($payment) use ($invoice) {
                    return [
                        'date' => $payment->paid_at?->toIso8601String(),
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'invoice_reference' => $invoice->reference,
                        'status' => $payment->status,
                    ];
                });
            })->sortByDesc('date')->values();

            return $this->successResponse([
                'balance' => (float) $balance,
                'breakdown' => $breakdown,
                'payment_methods' => $paymentMethods,
                'history' => $history,
            ], 'My fees retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve fees: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Pay fees (if enabled for students)
     */
    public function payFees(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'method' => 'required|in:mpesa,emola',
                'phone' => 'required|string|max:20',
            ]);

            // Redirect to mobile payment initiation
            // This would typically call the MobilePaymentController
            return $this->successResponse([
                'message' => 'Redirect to payment gateway',
                'payment_url' => '/v1/payments/mobile/initiate',
                'data' => [
                    'student_id' => $student->id,
                    'amount' => $request->amount,
                    'method' => $request->method,
                    'phone' => $request->phone,
                ],
            ], 'Payment initiation required');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to initiate payment: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Calculate attendance percentage
     */
    protected function calculateAttendancePercentage(int $studentId, ?string $from = null, ?string $to = null): float
    {
        $query = LessonAttendance::where('student_id', $studentId);

        if ($from && $to) {
            $query->whereHas('lesson', function ($q) use ($from, $to) {
                $q->whereBetween('lesson_date', [$from, $to]);
            });
        }

        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $present = $query->whereIn('status', ['present', 'late', 'online_present'])->count();

        return round(($present / $total) * 100, 2);
    }

    /**
     * Calculate average grade
     */
    protected function calculateAverageGrade(int $studentId): ?float
    {
        $average = GradeEntry::where('student_id', $studentId)
            ->avg('percentage_score') ?? GradeEntry::where('student_id', $studentId)
            ->avg('raw_score');

        return $average ? round($average, 2) : null;
    }

    /**
     * Get fees balance
     */
    protected function getFeesBalance(int $studentId): float
    {
        $invoices = Invoice::where('billable_type', Student::class)
            ->where('billable_id', $studentId)
            ->get();

        $balance = $invoices->sum(function ($invoice) {
            return $invoice->getRemainingBalance();
        });

        return (float) $balance;
    }

    /**
     * Get recent grades
     */
    protected function getRecentGrades(int $studentId, int $limit = 5): array
    {
        return GradeEntry::where('student_id', $studentId)
            ->with(['class.subject'])
            ->orderBy('assessment_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($entry) {
                    return [
                        'subject' => $entry->class->subject->name ?? 'Unknown',
                        'grade' => $entry->percentage_score ?? $entry->raw_score,
                        'date' => $entry->assessment_date->toIso8601String(),
                        'type' => $entry->assessment_type,
                    ];
            })
            ->toArray();
    }

    /**
     * Calculate grade trend
     */
    protected function calculateTrend($entries): string
    {
        if ($entries->count() < 2) {
            return 'stable';
        }

        $sorted = $entries->sortBy('assessment_date');
        $first = $sorted->first()->percentage_score ?? $sorted->first()->raw_score ?? 0;
        $last = $sorted->last()->percentage_score ?? $sorted->last()->raw_score ?? 0;

        if ($last > $first + 2) {
            return 'improving';
        } elseif ($last < $first - 2) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Calculate class average
     */
    protected function calculateClassAverage(?int $classId): ?float
    {
        if (!$classId) {
            return null;
        }

        $average = GradeEntry::where('class_id', $classId)
            ->avg('percentage_score') ?? GradeEntry::where('class_id', $classId)
            ->avg('raw_score');

        return $average ? round($average, 2) : null;
    }

    /**
     * Calculate student rank in class
     */
    protected function calculateRank(int $studentId, ?int $classId, float $studentGrade): ?int
    {
        if (!$classId) {
            return null;
        }

        // Get all students in class with their average grades
        $studentAverages = GradeEntry::where('class_id', $classId)
            ->select('student_id', \Illuminate\Support\Facades\DB::raw('AVG(percentage_score) as avg_grade'))
            ->groupBy('student_id')
            ->orderByDesc('avg_grade')
            ->get();

        $rank = 1;
        foreach ($studentAverages as $avg) {
            if ($avg->student_id === $studentId) {
                return $rank;
            }
            $rank++;
        }

        return null;
    }
}


<?php

namespace App\Http\Controllers\API\V1\Parent;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Resources\Parent\ChildSummaryResource;
use App\Http\Resources\Parent\ParentDashboardResource;
use App\Models\V1\Academic\GradeEntry;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Schedule\LessonAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentPortalController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get parent dashboard with children summary
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Get all children for this parent
            $familyRelationships = FamilyRelationship::where('guardian_user_id', $user->id)
                ->where('status', 'active')
                ->where('academic_access', true)
                ->with(['student' => function ($query) {
                    $query->with(['currentAcademicYear', 'school']);
                }])
                ->get();

            $children = $familyRelationships->map(function ($relationship) {
                $student = $relationship->student;
                if (!$student) {
                    return null;
                }

                // Get attendance percentage
                $attendancePercentage = $this->calculateAttendancePercentage($student->id);

                // Get average grade
                $averageGrade = $this->calculateAverageGrade($student->id);

                // Get fees status
                $feesStatus = $this->getFeesStatus($student->id);

                // Get recent grades (last 5)
                $recentGrades = $this->getRecentGrades($student->id, 5);

                // Get upcoming events (placeholder - implement when events system is ready)
                $upcomingEvents = [];

                return [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'class' => $student->current_grade_level ?? 'N/A',
                    'photo_url' => null, // TODO: Add photo URL when implemented
                    'summary' => [
                        'attendance_percentage' => $attendancePercentage,
                        'average_grade' => $averageGrade,
                        'fees_status' => $feesStatus['status'],
                        'fees_balance' => $feesStatus['balance'],
                        'recent_grades' => $recentGrades,
                        'upcoming_events' => $upcomingEvents,
                    ],
                ];
            })->filter();

            // Get alerts (overdue payments, low attendance, etc.)
            $alerts = $this->getAlerts($familyRelationships);

            // Get messages (placeholder - implement when messaging system is ready)
            $messages = [];

            return $this->successResponse([
                'children' => $children,
                'alerts' => $alerts,
                'messages' => $messages,
            ], 'Parent dashboard retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get child's grades
     */
    public function getChildGrades(Request $request, int $childId): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verify parent has access to this child
            $relationship = FamilyRelationship::where('guardian_user_id', $user->id)
                ->where('student_id', $childId)
                ->where('status', 'active')
                ->where('academic_access', true)
                ->first();

            if (!$relationship) {
                return $this->errorResponse('Access denied: You do not have access to this child', 403);
            }

            $student = Student::findOrFail($childId);

            // Get all grades
            $gradeEntries = GradeEntry::where('student_id', $childId)
                ->with(['class', 'academicTerm', 'class.subject', 'enteredBy'])
                ->orderBy('assessment_date', 'desc')
                ->get();

            // Group by subject
            $subjects = $gradeEntries->groupBy(function ($entry) {
                return $entry->class->subject->name ?? 'Unknown';
            })->map(function ($entries, $subjectName) {
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

                return [
                    'name' => $subjectName,
                    'teacher' => $teacher ? $teacher->name : 'N/A',
                    'current_grade' => round($currentGrade, 2),
                    'trend' => $this->calculateTrend($entries),
                    'assessments' => $grades,
                    'class_average' => $this->calculateClassAverage($entries->first()->class_id ?? null),
                ];
            })->values();

            $overallAverage = $gradeEntries->avg('percentage_score') ?? $gradeEntries->avg('raw_score');

            return $this->successResponse([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                    'class' => $student->current_grade_level,
                ],
                'overall_average' => round($overallAverage, 2),
                'subjects' => $subjects,
            ], 'Child grades retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve child grades: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get child's attendance
     */
    public function getChildAttendance(Request $request, int $childId): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verify parent has access to this child
            $relationship = FamilyRelationship::where('guardian_user_id', $user->id)
                ->where('student_id', $childId)
                ->where('status', 'active')
                ->where('academic_access', true)
                ->first();

            if (!$relationship) {
                return $this->errorResponse('Access denied: You do not have access to this child', 403);
            }

            $from = $request->get('from', now()->subMonth()->toDateString());
            $to = $request->get('to', now()->toDateString());

            $attendances = LessonAttendance::where('student_id', $childId)
                ->whereHas('lesson', function ($query) use ($from, $to) {
                    $query->whereBetween('date', [$from, $to]);
                })
                ->with(['lesson.schedule.subject', 'lesson.schedule.teacher'])
                ->orderBy('created_at', 'desc')
                ->get();

            $attendancePercentage = $this->calculateAttendancePercentage($childId, $from, $to);

            $summary = [
                'total_lessons' => $attendances->count(),
                'present' => $attendances->whereIn('status', ['present', 'late', 'online_present'])->count(),
                'absent' => $attendances->where('status', 'absent')->count(),
                'late' => $attendances->where('status', 'late')->count(),
                'excused' => $attendances->where('status', 'excused')->count(),
                'attendance_percentage' => $attendancePercentage,
            ];

            return $this->successResponse([
                'student_id' => $childId,
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
            ], 'Child attendance retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve child attendance: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get child's fees
     */
    public function getChildFees(Request $request, int $childId): JsonResponse
    {
        try {
            $user = auth('api')->user();

            // Verify parent has access to this child
            $relationship = FamilyRelationship::where('guardian_user_id', $user->id)
                ->where('student_id', $childId)
                ->where('status', 'active')
                ->where('financial_responsibility', true)
                ->first();

            if (!$relationship) {
                return $this->errorResponse('Access denied: You do not have access to this child\'s financial information', 403);
            }

            $student = Student::findOrFail($childId);

            // Get invoices for this student
            $invoices = Invoice::where('billable_type', Student::class)
                ->where('billable_id', $childId)
                ->with(['items', 'payments'])
                ->orderBy('due_at', 'desc')
                ->get();

            $currentBalance = $invoices->sum(function ($invoice) {
                return $invoice->getRemainingBalance();
            });

            $breakdown = $invoices->map(function ($invoice) {
                return [
                    'description' => $invoice->items->first()->description ?? 'Invoice ' . $invoice->reference,
                    'amount' => (float) $invoice->total,
                    'due_date' => $invoice->due_at?->toISOString(),
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
                        'date' => $payment->paid_at?->toISOString(),
                        'amount' => (float) $payment->amount,
                        'method' => $payment->method,
                        'invoice_reference' => $invoice->reference,
                        'status' => $payment->status,
                    ];
                });
            })->sortByDesc('date')->values();

            return $this->successResponse([
                'student' => [
                    'id' => $student->id,
                    'name' => $student->first_name . ' ' . $student->last_name,
                ],
                'current_balance' => (float) $currentBalance,
                'breakdown' => $breakdown,
                'payment_methods' => $paymentMethods,
                'history' => $history,
            ], 'Child fees retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve child fees: ' . $e->getMessage(),
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
                $q->whereBetween('date', [$from, $to]);
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
     * Get fees status
     */
    protected function getFeesStatus(int $studentId): array
    {
        $invoices = Invoice::where('billable_type', Student::class)
            ->where('billable_id', $studentId)
            ->get();

        $balance = $invoices->sum(function ($invoice) {
            return $invoice->getRemainingBalance();
        });

        $overdue = $invoices->filter(function ($invoice) {
            return $invoice->isOverdue();
        })->count();

        $status = 'up_to_date';
        if ($overdue > 0) {
            $status = 'overdue';
        } elseif ($balance > 0) {
            $status = 'pending';
        }

        return [
            'status' => $status,
            'balance' => (float) $balance,
            'overdue_count' => $overdue,
        ];
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
     * Get alerts for parent
     */
    protected function getAlerts($familyRelationships): array
    {
        $alerts = [];

        foreach ($familyRelationships as $relationship) {
            $student = $relationship->student;
            if (!$student) {
                continue;
            }

            // Check for overdue payments
            $feesStatus = $this->getFeesStatus($student->id);
            if ($feesStatus['status'] === 'overdue') {
                $alerts[] = [
                    'type' => 'payment',
                    'message' => 'Payment overdue for ' . $student->first_name,
                    'priority' => 'high',
                    'student_id' => $student->id,
                ];
            }

            // Check for low attendance
            $attendancePercentage = $this->calculateAttendancePercentage($student->id);
            if ($attendancePercentage < 80) {
                $alerts[] = [
                    'type' => 'attendance',
                    'message' => 'Low attendance for ' . $student->first_name . ' (' . $attendancePercentage . '%)',
                    'priority' => 'medium',
                    'student_id' => $student->id,
                ];
            }
        }

        return $alerts;
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
}


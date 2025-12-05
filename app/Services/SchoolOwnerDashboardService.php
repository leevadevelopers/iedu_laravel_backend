<?php

namespace App\Services;

use App\Models\V1\SIS\School\School;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Schedule\LessonAttendance;
use App\Models\V1\SIS\School\AcademicYear;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchoolOwnerDashboardService
{
    /**
     * Get dashboard statistics for a school
     */
    public function getStatistics(int $schoolId): array
    {
        $cacheKey = "school_owner_statistics_{$schoolId}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId) {
            $totalStudents = Student::where('school_id', $schoolId)
                ->where('enrollment_status', 'enrolled')
                ->count();
            
            $totalTeachers = Teacher::where('school_id', $schoolId)
                ->where('status', 'active')
                ->count();
            
            $outstandingPayments = Invoice::where('school_id', $schoolId)
                ->whereIn('status', ['issued', 'overdue'])
                ->sum('total');
            
            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();
            
            $attendanceThisWeek = LessonAttendance::where('school_id', $schoolId)
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->whereIn('status', ['present', 'late', 'online_present'])
                ->count();
            
            return [
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'outstanding_payments' => round((float) $outstandingPayments, 2),
                'attendance_this_week' => $attendanceThisWeek,
            ];
        });
    }

    /**
     * Get alerts for a school
     */
    public function getAlerts(int $schoolId): array
    {
        $cacheKey = "school_owner_alerts_{$schoolId}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId) {
            // Overdue payments (invoices with due_at > 30 days ago)
            $overduePayments = Invoice::where('school_id', $schoolId)
                ->where('status', 'overdue')
                ->where('due_at', '<=', Carbon::now()->subDays(30))
                ->count();
            
            // Check subscription/trial expiring
            $school = School::find($schoolId);
            $subscriptionExpiring = false;
            
            if ($school) {
                // Check trial_ends_at (if within 30 days)
                if ($school->trial_ends_at && $school->trial_ends_at->isFuture() && $school->trial_ends_at->diffInDays(now()) <= 30) {
                    $subscriptionExpiring = true;
                }
                
                // Check subscription end_date if subscription model exists
                // This is a placeholder - adjust based on actual subscription structure
            }
            
            // Upcoming events (placeholder - implement when events table exists)
            $upcomingEvents = 0; // TODO: Implement when events system is available
            
            return [
                'overdue_payments' => $overduePayments,
                'subscription_expiring' => $subscriptionExpiring,
                'upcoming_events' => $upcomingEvents,
            ];
        });
    }

    /**
     * Get revenue data for a school
     */
    public function getRevenue(int $schoolId, string $period = 'trimester'): array
    {
        $cacheKey = "school_owner_revenue_{$schoolId}_{$period}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $period) {
            $school = School::find($schoolId);
            
            // Determine period dates based on school's academic calendar
            $periodStart = $this->getPeriodStartDate($school, $period);
            $periodEnd = Carbon::now();
            
            // Total received (completed payments)
            $totalReceived = Payment::where('school_id', $schoolId)
                ->where('status', 'completed')
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$periodStart, $periodEnd])
                ->sum('amount');
            
            // Total expected (invoices issued)
            $totalExpected = Invoice::where('school_id', $schoolId)
                ->whereIn('status', ['issued', 'paid', 'partially_paid'])
                ->whereBetween('issued_at', [$periodStart, $periodEnd])
                ->sum('total');
            
            // Outstanding by month
            $outstandingByMonth = $this->getOutstandingByMonth($schoolId, $periodStart, $periodEnd);
            
            return [
                'total_received' => round((float) $totalReceived, 2),
                'total_expected' => round((float) $totalExpected, 2),
                'outstanding_by_month' => $outstandingByMonth,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ];
        });
    }

    /**
     * Get recent activity for a school
     */
    public function getRecentActivity(int $schoolId, int $limit = 10): array
    {
        $activities = [];
        
        // Recent student enrollments
        $recentEnrollments = Student::where('school_id', $schoolId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentEnrollments as $student) {
            $activities[] = [
                'type' => 'enrollment',
                'message' => "Novo aluno: {$student->first_name} {$student->last_name}",
                'time' => $this->getTimeAgo($student->created_at),
                'timestamp' => $student->created_at->toIso8601String(),
            ];
        }
        
        // Recent payments
        $recentPayments = Payment::where('school_id', $schoolId)
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'payment',
                'message' => "Pagamento recebido: " . number_format($payment->amount, 2, ',', '.') . " MZN",
                'time' => $this->getTimeAgo($payment->created_at),
                'timestamp' => $payment->created_at->toIso8601String(),
            ];
        }
        
        // Recent invoices
        $recentInvoices = Invoice::where('school_id', $schoolId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentInvoices as $invoice) {
            $activities[] = [
                'type' => 'invoice',
                'message' => "Nova fatura emitida: " . number_format($invoice->total, 2, ',', '.') . " MZN",
                'time' => $this->getTimeAgo($invoice->created_at),
                'timestamp' => $invoice->created_at->toIso8601String(),
            ];
        }
        
        // Sort by timestamp and limit
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get attendance statistics for a school
     */
    public function getAttendanceStats(int $schoolId, string $period = 'week'): array
    {
        $cacheKey = "school_owner_attendance_{$schoolId}_{$period}";
        
        return Cache::remember($cacheKey, 300, function () use ($schoolId, $period) {
            $startDate = $this->getPeriodStartDate(null, $period);
            $endDate = Carbon::now();
            
            $totalAttendance = LessonAttendance::where('school_id', $schoolId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $presentCount = LessonAttendance::where('school_id', $schoolId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['present', 'late', 'online_present'])
                ->count();
            
            $absentCount = LessonAttendance::where('school_id', $schoolId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['absent', 'excused'])
                ->count();
            
            $attendanceRate = $totalAttendance > 0 
                ? round(($presentCount / $totalAttendance) * 100, 2) 
                : 0;
            
            return [
                'total_attendance' => $totalAttendance,
                'present_count' => $presentCount,
                'absent_count' => $absentCount,
                'attendance_rate' => $attendanceRate,
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ];
        });
    }

    /**
     * Get period start date based on school's academic calendar
     */
    private function getPeriodStartDate(?School $school, string $period): Carbon
    {
        if ($period === 'trimester') {
            // Get current academic year
            if ($school) {
                $academicYear = AcademicYear::where('school_id', $school->id)
                    ->where('is_current', true)
                    ->first();
                
                if ($academicYear) {
                    // Calculate trimester based on academic year start
                    $yearStart = Carbon::parse($academicYear->start_date);
                    $now = Carbon::now();
                    
                    // Determine which trimester we're in
                    $monthsSinceStart = $yearStart->diffInMonths($now);
                    
                    if ($monthsSinceStart < 4) {
                        // First trimester
                        return $yearStart;
                    } elseif ($monthsSinceStart < 8) {
                        // Second trimester
                        return $yearStart->copy()->addMonths(4);
                    } else {
                        // Third trimester
                        return $yearStart->copy()->addMonths(8);
                    }
                }
            }
            
            // Fallback: last 4 months
            return Carbon::now()->subMonths(4)->startOfMonth();
        }
        
        if ($period === 'semester') {
            if ($school) {
                $academicYear = AcademicYear::where('school_id', $school->id)
                    ->where('is_current', true)
                    ->first();
                
                if ($academicYear) {
                    $yearStart = Carbon::parse($academicYear->start_date);
                    $now = Carbon::now();
                    
                    // Determine which semester
                    if ($yearStart->diffInMonths($now) < 6) {
                        return $yearStart;
                    } else {
                        return $yearStart->copy()->addMonths(6);
                    }
                }
            }
            
            // Fallback: last 6 months
            return Carbon::now()->subMonths(6)->startOfMonth();
        }
        
        if ($period === 'week') {
            return Carbon::now()->startOfWeek();
        }
        
        if ($period === 'month') {
            return Carbon::now()->startOfMonth();
        }
        
        // Default: last 30 days
        return Carbon::now()->subDays(30);
    }

    /**
     * Get outstanding payments grouped by month
     */
    private function getOutstandingByMonth(int $schoolId, Carbon $startDate, Carbon $endDate): array
    {
        $invoices = Invoice::where('school_id', $schoolId)
            ->whereIn('status', ['issued', 'overdue'])
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->get();
        
        $grouped = [];
        
        foreach ($invoices as $invoice) {
            $month = Carbon::parse($invoice->issued_at)->format('Y-m');
            $monthLabel = Carbon::parse($invoice->issued_at)->format('M Y');
            
            if (!isset($grouped[$month])) {
                $grouped[$month] = [
                    'month' => $monthLabel,
                    'amount' => 0,
                ];
            }
            
            $grouped[$month]['amount'] += $invoice->total;
        }
        
        // Sort by month and format
        ksort($grouped);
        
        return array_values($grouped);
    }

    /**
     * Get human-readable time ago
     */
    private function getTimeAgo(Carbon $date): string
    {
        $diff = now()->diffInHours($date);
        
        if ($diff < 1) {
            return 'há menos de 1 hora';
        } elseif ($diff < 24) {
            return "há {$diff}h";
        } else {
            $days = now()->diffInDays($date);
            return "há {$days} dias";
        }
    }

    /**
     * Clear all dashboard caches for a school
     */
    public function clearCache(int $schoolId): void
    {
        Cache::forget("school_owner_statistics_{$schoolId}");
        Cache::forget("school_owner_alerts_{$schoolId}");
        Cache::forget("school_owner_revenue_{$schoolId}_trimester");
        Cache::forget("school_owner_revenue_{$schoolId}_semester");
        Cache::forget("school_owner_revenue_{$schoolId}_month");
        Cache::forget("school_owner_attendance_{$schoolId}_week");
        Cache::forget("school_owner_attendance_{$schoolId}_month");
    }
}


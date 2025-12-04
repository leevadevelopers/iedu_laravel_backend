<?php

namespace App\Services;

use App\Models\User;
use App\Models\V1\SIS\School\School;
use App\Models\V1\Financial\Payment;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SuperAdminDashboardService
{
    /**
     * Get platform statistics
     */
    public function getStatistics(): array
    {
        return Cache::remember('super_admin_statistics', 300, function () {
            $totalSchools = School::withoutGlobalScopes()->count();
            $totalUsers = User::withoutGlobalScopes()->whereHas('roles', function ($query) {
                $query->where('name', '!=', 'super_admin');
            })->count();
            
            // Calculate MRR from active schools with subscription plans
            $mrr = $this->calculateMRR();
            
            // Calculate growth percentage (month-over-month)
            $growthPercentage = $this->calculateGrowthPercentage();
            
            return [
                'total_schools' => $totalSchools,
                'total_users' => $totalUsers,
                'mrr' => $mrr,
                'growth_percentage' => $growthPercentage,
            ];
        });
    }

    /**
     * Calculate Monthly Recurring Revenue (MRR)
     */
    private function calculateMRR(): float
    {
        // Define plan prices (in MZN)
        $planPrices = [
            'basic' => 5000,
            'standard' => 10000,
            'premium' => 15000,
            'enterprise' => 25000,
        ];

        $mrr = 0;
        
        // Get active schools with subscription plans
        $schools = School::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('subscription_plan')
            ->get();

        foreach ($schools as $school) {
            $plan = strtolower($school->subscription_plan);
            if (isset($planPrices[$plan])) {
                $mrr += $planPrices[$plan];
            }
        }

        return round($mrr, 2);
    }

    /**
     * Calculate growth percentage (month-over-month for schools)
     */
    private function calculateGrowthPercentage(): float
    {
        $currentMonth = School::withoutGlobalScopes()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = School::withoutGlobalScopes()
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        if ($lastMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $activities = [];

        // Recent school upgrades (subscription plan changes)
        $recentUpgrades = School::withoutGlobalScopes()
            ->whereNotNull('subscription_plan')
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentUpgrades as $school) {
            $activities[] = [
                'type' => 'upgrade',
                'message' => "{$school->display_name} - Upgrade para {$school->subscription_plan}",
                'time' => $this->getTimeAgo($school->updated_at),
                'school_id' => $school->id,
                'timestamp' => $school->updated_at->toIso8601String(),
            ];
        }

        // Recent school registrations
        $recentRegistrations = School::withoutGlobalScopes()
            ->where('created_at', '>=', now()->subDays(1))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentRegistrations->count() > 0) {
            $activities[] = [
                'type' => 'registration',
                'message' => "{$recentRegistrations->count()} novas escolas registradas hoje",
                'time' => 'hoje',
                'timestamp' => now()->toIso8601String(),
            ];
        }

        // Overdue payments
        $overduePayments = Payment::withoutGlobalScopes()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subDays(5))
            ->with(['invoice' => function ($query) {
                $query->select('id', 'school_id');
            }])
            ->limit(5)
            ->get();

        foreach ($overduePayments as $payment) {
            if ($payment->invoice && $payment->invoice->school_id) {
                $school = School::withoutGlobalScopes()->find($payment->invoice->school_id);
                if ($school) {
                    $daysOverdue = now()->diffInDays($payment->created_at);
                    $activities[] = [
                        'type' => 'overdue',
                        'message' => "{$school->display_name} - Pagamento atrasado ({$daysOverdue} dias)",
                        'time' => $this->getTimeAgo($payment->created_at),
                        'school_id' => $school->id,
                        'timestamp' => $payment->created_at->toIso8601String(),
                    ];
                }
            }
        }

        // Sort by timestamp and limit
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Get growth chart data (last 6 months)
     */
    public function getGrowthChart(): array
    {
        return Cache::remember('super_admin_growth_chart', 300, function () {
            $months = [];
            $schoolsData = [];
            $usersData = [];
            $revenueData = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthName = $date->format('M');
                $months[] = $monthName;

                // Schools count up to this month
                $schoolsData[] = School::withoutGlobalScopes()
                    ->where('created_at', '<=', $date->endOfMonth())
                    ->count();

                // Users count up to this month
                $usersData[] = User::withoutGlobalScopes()
                    ->whereHas('roles', function ($query) {
                        $query->where('name', '!=', 'super_admin');
                    })
                    ->where('created_at', '<=', $date->endOfMonth())
                    ->count();

                // Revenue for this month (simplified - using MRR calculation)
                $revenueData[] = $this->calculateMonthlyRevenue($date);
            }

            return [
                'months' => $months,
                'schools' => $schoolsData,
                'users' => $usersData,
                'revenue' => $revenueData,
            ];
        });
    }

    /**
     * Calculate monthly revenue for a specific month
     */
    private function calculateMonthlyRevenue(Carbon $date): float
    {
        $planPrices = [
            'basic' => 5000,
            'standard' => 10000,
            'premium' => 15000,
            'enterprise' => 25000,
        ];

        $revenue = 0;
        
        $schools = School::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('subscription_plan')
            ->where('created_at', '<=', $date->endOfMonth())
            ->get();

        foreach ($schools as $school) {
            $plan = strtolower($school->subscription_plan);
            if (isset($planPrices[$plan])) {
                $revenue += $planPrices[$plan];
            }
        }

        return round($revenue, 2);
    }

    /**
     * Get alerts
     */
    public function getAlerts(): array
    {
        // Overdue payments count
        $overduePayments = Payment::withoutGlobalScopes()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subDays(5))
            ->count();

        // Pending support tickets (placeholder - implement when ticket system exists)
        $pendingTickets = 12; // TODO: Replace with actual ticket count

        // Uptime percentage (placeholder - implement when monitoring exists)
        $uptimePercentage = 98.5; // TODO: Replace with actual uptime calculation

        return [
            'overdue_payments' => $overduePayments,
            'pending_tickets' => $pendingTickets,
            'uptime_percentage' => $uptimePercentage,
        ];
    }

    /**
     * Get recent schools
     */
    public function getRecentSchools(int $limit = 10): array
    {
        $schools = School::withoutGlobalScopes()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $schools->map(function ($school) {
            return [
                'id' => $school->id,
                'name' => $school->display_name,
                'plan' => ucfirst($school->subscription_plan ?? 'N/A'),
                'students' => $school->current_enrollment ?? 0,
                'status' => $school->status,
                'subscription_status' => $this->getSubscriptionStatus($school),
                'created_at' => $school->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Get subscription status for a school
     */
    private function getSubscriptionStatus(School $school): string
    {
        if ($school->isOnTrial()) {
            return 'trial';
        }

        if ($school->status === 'active' && $school->subscription_plan) {
            return 'active';
        }

        return 'inactive';
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
     * Clear all dashboard caches
     */
    public function clearCache(): void
    {
        Cache::forget('super_admin_statistics');
        Cache::forget('super_admin_growth_chart');
    }
}


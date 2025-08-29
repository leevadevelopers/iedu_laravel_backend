<?php

namespace App\Services\V1\Transport;

use App\Models\V1\SIS\School\School;
use App\Models\V1\Transport\TransportIncident;
use App\Models\V1\Transport\TransportDailyLog;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Models\V1\Transport\TransportTracking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransportReportService
{
    /**
     * Generate attendance report
     */
    public function generateAttendanceReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        $query = StudentTransportSubscription::with(['student', 'transportRoute'])
            ->whereHas('student', function($q) use ($school) {
                $q->where('school_id', $school->id);
            })
            ->where('status', 'active');

        if (isset($parameters['route_id'])) {
            $query->where('transport_route_id', $parameters['route_id']);
        }

        $subscriptions = $query->get();

        $attendanceData = [];
        foreach ($subscriptions as $subscription) {
            $student = $subscription->student;
            $route = $subscription->transportRoute;

            // Get attendance records for the date range
            $attendanceRecords = $this->getStudentAttendanceRecords($student->id, $dateRange);

            $attendanceData[] = [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'grade' => $student->grade,
                'route_name' => $route->route_name ?? 'N/A',
                'pickup_stop' => $subscription->pickup_stop,
                'dropoff_stop' => $subscription->dropoff_stop,
                'total_days' => $dateRange['total_days'],
                'days_present' => $attendanceRecords['present'],
                'days_absent' => $attendanceRecords['absent'],
                'attendance_rate' => $this->calculateAttendanceRate($attendanceRecords['present'], $dateRange['total_days']),
                'late_arrivals' => $attendanceRecords['late'],
                'early_departures' => $attendanceRecords['early_departures']
            ];
        }

        return [
            'report_type' => 'attendance',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'total_students' => count($attendanceData),
            'data' => $attendanceData,
            'summary' => $this->generateAttendanceSummary($attendanceData)
        ];
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        // Get route performance data
        $routePerformance = $this->getRoutePerformanceData($school, $dateRange);

        // Get driver performance data
        $driverPerformance = $this->getDriverPerformanceData($school, $dateRange);

        // Get bus performance data
        $busPerformance = $this->getBusPerformanceData($school, $dateRange);

        return [
            'report_type' => 'performance',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'route_performance' => $routePerformance,
            'driver_performance' => $driverPerformance,
            'bus_performance' => $busPerformance,
            'summary' => $this->generatePerformanceSummary($routePerformance, $driverPerformance, $busPerformance)
        ];
    }

    /**
     * Generate financial report
     */
    public function generateFinancialReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        // Get subscription revenue
        $subscriptionRevenue = $this->getSubscriptionRevenue($school, $dateRange);

        // Get operational costs
        $operationalCosts = $this->getOperationalCosts($school, $dateRange);

        // Get maintenance costs
        $maintenanceCosts = $this->getMaintenanceCosts($school, $dateRange);

        // Get fuel costs
        $fuelCosts = $this->getFuelCosts($school, $dateRange);

        $totalRevenue = $subscriptionRevenue['total'];
        $totalCosts = $operationalCosts['total'] + $maintenanceCosts['total'] + $fuelCosts['total'];
        $netProfit = $totalRevenue - $totalCosts;

        return [
            'report_type' => 'financial',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'revenue' => $subscriptionRevenue,
            'costs' => [
                'operational' => $operationalCosts,
                'maintenance' => $maintenanceCosts,
                'fuel' => $fuelCosts,
                'total' => $totalCosts
            ],
            'profitability' => [
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
                'cost_per_student' => $this->getStudentCount($school) > 0 ? $totalCosts / $this->getStudentCount($school) : 0
            ]
        ];
    }

    /**
     * Generate safety report
     */
    public function generateSafetyReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        // Get incident data
        $incidents = TransportIncident::where('school_id', $school->id)
            ->whereBetween('incident_datetime', [$dateRange['start'], $dateRange['end']])
            ->with(['fleetBus', 'transportRoute', 'reportedBy'])
            ->get();

        $incidentAnalysis = $this->analyzeIncidents($incidents);

        // Get safety metrics
        $safetyMetrics = $this->calculateSafetyMetrics($school, $dateRange);

        return [
            'report_type' => 'safety',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'incidents' => [
                'total' => $incidents->count(),
                'by_type' => $incidentAnalysis['by_type'],
                'by_severity' => $incidentAnalysis['by_severity'],
                'by_route' => $incidentAnalysis['by_route'],
                'trends' => $incidentAnalysis['trends']
            ],
            'safety_metrics' => $safetyMetrics,
            'recommendations' => $this->generateSafetyRecommendations($incidentAnalysis, $safetyMetrics)
        ];
    }

    /**
     * Generate utilization report
     */
    public function generateUtilizationReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        // Get fleet utilization
        $fleetUtilization = $this->getFleetUtilization($school, $dateRange);

        // Get route utilization
        $routeUtilization = $this->getRouteUtilization($school, $dateRange);

        // Get capacity utilization
        $capacityUtilization = $this->getCapacityUtilization($school, $dateRange);

        return [
            'report_type' => 'utilization',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'fleet_utilization' => $fleetUtilization,
            'route_utilization' => $routeUtilization,
            'capacity_utilization' => $capacityUtilization,
            'efficiency_metrics' => $this->calculateEfficiencyMetrics($fleetUtilization, $routeUtilization, $capacityUtilization)
        ];
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport(School $school, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);
        $customMetrics = $parameters['metrics'] ?? [];

        $reportData = [];

        foreach ($customMetrics as $metric) {
            switch ($metric) {
                case 'student_transport_patterns':
                    $reportData[$metric] = $this->getStudentTransportPatterns($school, $dateRange);
                    break;
                case 'route_optimization':
                    $reportData[$metric] = $this->getRouteOptimizationData($school, $dateRange);
                    break;
                case 'maintenance_schedule':
                    $reportData[$metric] = $this->getMaintenanceScheduleData($school, $dateRange);
                    break;
                case 'driver_scheduling':
                    $reportData[$metric] = $this->getDriverSchedulingData($school, $dateRange);
                    break;
                case 'cost_analysis':
                    $reportData[$metric] = $this->getDetailedCostAnalysis($school, $dateRange);
                    break;
                default:
                    $reportData[$metric] = $this->getGenericMetricData($school, $metric, $dateRange);
            }
        }

        return [
            'report_type' => 'custom',
            'school_name' => $school->name,
            'date_range' => $dateRange,
            'requested_metrics' => $customMetrics,
            'data' => $reportData
        ];
    }

    /**
     * Get date range from parameters
     */
    private function getDateRange(array $parameters): array
    {
        $startDate = $parameters['start_date'] ?? now()->startOfMonth();
        $endDate = $parameters['end_date'] ?? now()->endOfMonth();

        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate);
        }
        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate);
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
            'total_days' => $startDate->diffInDays($endDate) + 1
        ];
    }

    /**
     * Get student attendance records
     */
    private function getStudentAttendanceRecords(int $studentId, array $dateRange): array
    {
        // This would typically query actual attendance records
        // For now, return mock data structure
        return [
            'present' => rand(15, 20),
            'absent' => rand(0, 5),
            'late' => rand(0, 3),
            'early_departures' => rand(0, 2)
        ];
    }

    /**
     * Calculate attendance rate
     */
    private function calculateAttendanceRate(int $present, int $total): float
    {
        return $total > 0 ? round(($present / $total) * 100, 2) : 0;
    }

    /**
     * Generate attendance summary
     */
    private function generateAttendanceSummary(array $attendanceData): array
    {
        $totalStudents = count($attendanceData);
        $avgAttendanceRate = collect($attendanceData)->avg('attendance_rate');

        return [
            'total_students' => $totalStudents,
            'average_attendance_rate' => round($avgAttendanceRate, 2),
            'students_above_90_percent' => collect($attendanceData)->where('attendance_rate', '>=', 90)->count(),
            'students_below_75_percent' => collect($attendanceData)->where('attendance_rate', '<', 75)->count()
        ];
    }

    /**
     * Get route performance data
     */
    private function getRoutePerformanceData(School $school, array $dateRange): array
    {
        $routes = TransportRoute::where('school_id', $school->id)->get();
        $routeData = [];

        foreach ($routes as $route) {
            $dailyLogs = TransportDailyLog::where('transport_route_id', $route->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $routeData[] = [
                'route_id' => $route->id,
                'route_name' => $route->route_name,
                'total_trips' => $dailyLogs->count(),
                'completed_trips' => $dailyLogs->where('status', 'completed')->count(),
                'on_time_percentage' => $this->calculateOnTimePercentage($dailyLogs),
                'average_duration' => $this->calculateAverageTripDuration($dailyLogs),
                'student_satisfaction' => $this->getStudentSatisfactionScore($route->id)
            ];
        }

        return $routeData;
    }

    /**
     * Get driver performance data
     */
    private function getDriverPerformanceData(School $school, array $dateRange): array
    {
        $drivers = User::role('driver')->where('school_id', $school->id)->get();
        $driverData = [];

        foreach ($drivers as $driver) {
            $dailyLogs = TransportDailyLog::where('driver_id', $driver->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $driverData[] = [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'total_trips' => $dailyLogs->count(),
                'completed_trips' => $dailyLogs->where('status', 'completed')->count(),
                'safety_score' => $this->calculateDriverSafetyScore($driver->id, $dateRange),
                'incident_count' => $this->getDriverIncidentCount($driver->id, $dateRange),
                'fuel_efficiency' => $this->calculateFuelEfficiency($dailyLogs)
            ];
        }

        return $driverData;
    }

    /**
     * Get bus performance data
     */
    private function getBusPerformanceData(School $school, array $dateRange): array
    {
        $buses = FleetBus::where('school_id', $school->id)->get();
        $busData = [];

        foreach ($buses as $bus) {
            $dailyLogs = TransportDailyLog::where('fleet_bus_id', $bus->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $busData[] = [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'total_trips' => $dailyLogs->count(),
                'distance_covered' => $this->calculateTotalDistance($dailyLogs),
                'fuel_consumption' => $this->calculateFuelConsumption($dailyLogs),
                'maintenance_status' => $this->getMaintenanceStatus($bus),
                'reliability_score' => $this->calculateReliabilityScore($bus->id, $dateRange)
            ];
        }

        return $busData;
    }

    /**
     * Generate performance summary
     */
    private function generatePerformanceSummary(array $routePerformance, array $driverPerformance, array $busPerformance): array
    {
        return [
            'total_routes' => count($routePerformance),
            'total_drivers' => count($driverPerformance),
            'total_buses' => count($busPerformance),
            'average_on_time_percentage' => collect($routePerformance)->avg('on_time_percentage'),
            'average_safety_score' => collect($driverPerformance)->avg('safety_score'),
            'average_reliability_score' => collect($busPerformance)->avg('reliability_score')
        ];
    }

    /**
     * Get subscription revenue
     */
    private function getSubscriptionRevenue(School $school, array $dateRange): array
    {
        // This would query actual subscription payments
        // For now, return mock data
        return [
            'monthly_subscriptions' => 5000,
            'annual_subscriptions' => 15000,
            'one_time_payments' => 2000,
            'total' => 22000
        ];
    }

    /**
     * Get operational costs
     */
    private function getOperationalCosts(School $school, array $dateRange): array
    {
        return [
            'driver_salaries' => 8000,
            'fuel' => 3000,
            'maintenance' => 2000,
            'insurance' => 1500,
            'total' => 14500
        ];
    }

    /**
     * Get maintenance costs
     */
    private function getMaintenanceCosts(School $school, array $dateRange): array
    {
        return [
            'preventive_maintenance' => 1500,
            'repairs' => 800,
            'parts' => 600,
            'labor' => 400,
            'total' => 3300
        ];
    }

    /**
     * Get fuel costs
     */
    private function getFuelCosts(School $school, array $dateRange): array
    {
        return [
            'diesel' => 2500,
            'gasoline' => 500,
            'total' => 3000
        ];
    }

    /**
     * Get student count
     */
    private function getStudentCount(School $school): int
    {
        return StudentTransportSubscription::whereHas('student', function($q) use ($school) {
            $q->where('school_id', $school->id);
        })->count();
    }

    /**
     * Analyze incidents
     */
    private function analyzeIncidents(Collection $incidents): array
    {
        return [
            'by_type' => $incidents->groupBy('incident_type')->map->count(),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
            'by_route' => $incidents->groupBy('transportRoute.route_name')->map->count(),
            'trends' => $this->calculateIncidentTrends($incidents)
        ];
    }

    /**
     * Calculate safety metrics
     */
    private function calculateSafetyMetrics(School $school, array $dateRange): array
    {
        $totalTrips = TransportDailyLog::where('school_id', $school->id)
            ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
            ->count();

        $totalIncidents = TransportIncident::where('school_id', $school->id)
            ->whereBetween('incident_datetime', [$dateRange['start'], $dateRange['end']])
            ->count();

        return [
            'total_trips' => $totalTrips,
            'total_incidents' => $totalIncidents,
            'incident_rate' => $totalTrips > 0 ? ($totalIncidents / $totalTrips) * 100 : 0,
            'safety_score' => $this->calculateOverallSafetyScore($school, $dateRange)
        ];
    }

    /**
     * Generate safety recommendations
     */
    private function generateSafetyRecommendations(array $incidentAnalysis, array $safetyMetrics): array
    {
        $recommendations = [];

        if ($incidentAnalysis['by_severity']['critical'] > 0) {
            $recommendations[] = 'Immediate review of critical incident procedures required';
        }

        if ($safetyMetrics['incident_rate'] > 5) {
            $recommendations[] = 'Consider additional driver training and safety protocols';
        }

        return $recommendations;
    }

    /**
     * Get fleet utilization
     */
    private function getFleetUtilization(School $school, array $dateRange): array
    {
        $buses = FleetBus::where('school_id', $school->id)->get();
        $utilizationData = [];

        foreach ($buses as $bus) {
            $dailyLogs = TransportDailyLog::where('fleet_bus_id', $bus->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $utilizationData[] = [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'total_days' => $dateRange['total_days'],
                'days_in_use' => $dailyLogs->count(),
                'utilization_rate' => ($dailyLogs->count() / $dateRange['total_days']) * 100,
                'average_hours_per_day' => $this->calculateAverageHoursPerDay($dailyLogs)
            ];
        }

        return $utilizationData;
    }

    /**
     * Get route utilization
     */
    private function getRouteUtilization(School $school, array $dateRange): array
    {
        $routes = TransportRoute::where('school_id', $school->id)->get();
        $utilizationData = [];

        foreach ($routes as $route) {
            $dailyLogs = TransportDailyLog::where('transport_route_id', $route->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $utilizationData[] = [
                'route_id' => $route->id,
                'route_name' => $route->route_name,
                'total_days' => $dateRange['total_days'],
                'days_operated' => $dailyLogs->count(),
                'utilization_rate' => ($dailyLogs->count() / $dateRange['total_days']) * 100,
                'average_students_per_trip' => $this->calculateAverageStudentsPerTrip($dailyLogs)
            ];
        }

        return $utilizationData;
    }

    /**
     * Get capacity utilization
     */
    private function getCapacityUtilization(School $school, array $dateRange): array
    {
        $buses = FleetBus::where('school_id', $school->id)->get();
        $capacityData = [];

        foreach ($buses as $bus) {
            $dailyLogs = TransportDailyLog::where('fleet_bus_id', $bus->id)
                ->whereBetween('log_date', [$dateRange['start'], $dateRange['end']])
                ->get();

            $capacityData[] = [
                'bus_id' => $bus->id,
                'license_plate' => $bus->license_plate,
                'total_capacity' => $bus->capacity,
                'average_utilization' => $dailyLogs->avg('students_picked_up') ?? 0,
                'capacity_utilization_rate' => $bus->capacity > 0 ?
                    (($dailyLogs->avg('students_picked_up') ?? 0) / $bus->capacity) * 100 : 0
            ];
        }

        return $capacityData;
    }

    /**
     * Calculate efficiency metrics
     */
    private function calculateEfficiencyMetrics(array $fleetUtilization, array $routeUtilization, array $capacityUtilization): array
    {
        return [
            'average_fleet_utilization' => collect($fleetUtilization)->avg('utilization_rate'),
            'average_route_utilization' => collect($routeUtilization)->avg('utilization_rate'),
            'average_capacity_utilization' => collect($capacityUtilization)->avg('capacity_utilization_rate'),
            'overall_efficiency_score' => $this->calculateOverallEfficiencyScore($fleetUtilization, $routeUtilization, $capacityUtilization)
        ];
    }

    // Helper methods for calculations
    private function calculateOnTimePercentage($dailyLogs): float
    {
        // Implementation would check against scheduled times
        return rand(85, 98);
    }

    private function calculateAverageTripDuration($dailyLogs): float
    {
        // Implementation would calculate actual trip durations
        return rand(25, 45);
    }

    private function getStudentSatisfactionScore(int $routeId): float
    {
        // Implementation would query satisfaction surveys
        return rand(3.5, 5.0);
    }

    private function calculateDriverSafetyScore(int $driverId, array $dateRange): float
    {
        // Implementation would calculate based on incidents, violations, etc.
        return rand(85, 100);
    }

    private function getDriverIncidentCount(int $driverId, array $dateRange): int
    {
        return TransportIncident::where('reported_by', $driverId)
            ->whereBetween('incident_datetime', [$dateRange['start'], $dateRange['end']])
            ->count();
    }

    private function calculateFuelEfficiency($dailyLogs): float
    {
        // Implementation would calculate actual fuel efficiency
        return rand(8.5, 12.5);
    }

    private function calculateTotalDistance($dailyLogs): float
    {
        return $dailyLogs->sum(function($log) {
            if ($log->odometer_start && $log->odometer_end) {
                return $log->odometer_end - $log->odometer_start;
            }
            return 0;
        });
    }

    private function calculateFuelConsumption($dailyLogs): float
    {
        return $dailyLogs->sum(function($log) {
            if ($log->fuel_level_start && $log->fuel_level_end) {
                return $log->fuel_level_start - $log->fuel_level_end;
            }
            return 0;
        });
    }

    private function getMaintenanceStatus($bus): string
    {
        // Implementation would check actual maintenance status
        return ['good', 'needs_attention', 'maintenance_due'][rand(0, 2)];
    }

    private function calculateReliabilityScore(int $busId, array $dateRange): float
    {
        // Implementation would calculate based on breakdowns, delays, etc.
        return rand(80, 95);
    }

    private function calculateIncidentTrends($incidents): array
    {
        // Implementation would analyze incident trends over time
        return [
            'trend' => 'decreasing',
            'change_percentage' => -15.5
        ];
    }

    private function calculateOverallSafetyScore(School $school, array $dateRange): float
    {
        // Implementation would calculate comprehensive safety score
        return rand(85, 95);
    }

    private function calculateAverageHoursPerDay($dailyLogs): float
    {
        // Implementation would calculate actual hours
        return rand(6, 10);
    }

    private function calculateAverageStudentsPerTrip($dailyLogs): float
    {
        return $dailyLogs->avg('students_picked_up') ?? 0;
    }

    private function calculateOverallEfficiencyScore(array $fleetUtilization, array $routeUtilization, array $capacityUtilization): float
    {
        $fleetScore = collect($fleetUtilization)->avg('utilization_rate');
        $routeScore = collect($routeUtilization)->avg('utilization_rate');
        $capacityScore = collect($capacityUtilization)->avg('capacity_utilization_rate');

        return round(($fleetScore + $routeScore + $capacityScore) / 3, 2);
    }

    // Custom report helper methods
    private function getStudentTransportPatterns(School $school, array $dateRange): array
    {
        // Implementation would analyze student transport patterns
        return ['data' => 'Student transport pattern analysis'];
    }

    private function getRouteOptimizationData(School $school, array $dateRange): array
    {
        // Implementation would provide route optimization data
        return ['data' => 'Route optimization analysis'];
    }

    private function getMaintenanceScheduleData(School $school, array $dateRange): array
    {
        // Implementation would provide maintenance schedule data
        return ['data' => 'Maintenance schedule analysis'];
    }

    private function getDriverSchedulingData(School $school, array $dateRange): array
    {
        // Implementation would provide driver scheduling data
        return ['data' => 'Driver scheduling analysis'];
    }

    private function getDetailedCostAnalysis(School $school, array $dateRange): array
    {
        // Implementation would provide detailed cost analysis
        return ['data' => 'Detailed cost analysis'];
    }

    private function getGenericMetricData(School $school, string $metric, array $dateRange): array
    {
        // Implementation would provide generic metric data
        return ['data' => "Generic data for metric: {$metric}"];
    }
}

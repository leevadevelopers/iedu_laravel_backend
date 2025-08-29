<?php

namespace App\Services\V1\Transport;

use App\Models\User;
use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\Transport\TransportDailyLog;
use App\Models\V1\Transport\TransportIncident;
use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusRouteAssignment;
use App\Models\V1\Transport\StudentTransportSubscription;
// Events will be created as needed
// use App\Events\V1\Transport\RouteStarted;
// use App\Events\V1\Transport\RouteCompleted;
// use App\Events\V1\Transport\DailyChecklistSubmitted;
// use App\Events\V1\Transport\IncidentReported;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DriverPortalService
{
    /**
     * Get driver dashboard data
     */
    public function getDashboard(User $driver): array
    {
        $today = now()->toDateString();

        // Get today's routes
        $todayRoutes = $this->getTodayRoutes($driver);

        // Get assigned bus
        $assignedBus = $this->getAssignedBus($driver);

        // Get recent incidents
        $recentIncidents = $this->getRecentIncidents($driver);

        // Get today's statistics
        $todayStats = $this->getTodayStatistics($driver, $today);

        return [
            'today_routes' => $todayRoutes,
            'assigned_bus' => $assignedBus,
            'recent_incidents' => $recentIncidents,
            'today_stats' => $todayStats,
            'current_route_status' => $this->getCurrentRouteStatus($driver),
            'next_departure' => $this->getNextDepartureTime($driver),
            'alerts' => $this->getDriverAlerts($driver)
        ];
    }

    /**
     * Get today's routes for the driver
     */
    public function getTodayRoutes(User $driver): Collection
    {
        return BusRouteAssignment::with(['transportRoute.busStops', 'fleetBus'])
            ->where('driver_id', $driver->id)
            ->whereDate('assigned_date', '<=', now())
            ->where(function($query) {
                $query->whereNull('valid_until')
                      ->orWhereDate('valid_until', '>=', now());
            })
            ->where('status', 'active')
            ->get()
            ->map(function($assignment) {
                $route = $assignment->transportRoute;
                $route->estimated_departure = $this->calculateDepartureTime($route);
                $route->estimated_arrival = $this->calculateArrivalTime($route);
                $route->current_status = $this->getRouteStatus($assignment);
                return $route;
            });
    }

    /**
     * Get students assigned to driver's routes
     */
    public function getAssignedStudents(User $driver): Collection
    {
        $routeIds = $this->getTodayRoutes($driver)->pluck('id');

        return StudentTransportSubscription::with(['student', 'busStop'])
            ->whereIn('transport_route_id', $routeIds)
            ->where('status', 'active')
            ->get()
            ->groupBy('transport_route_id')
            ->map(function($subscriptions, $routeId) {
                return [
                    'route_id' => $routeId,
                    'students' => $subscriptions->map(function($sub) {
                        return [
                            'id' => $sub->student->id,
                            'name' => $sub->student->full_name,
                            'grade' => $sub->student->grade,
                            'pickup_stop' => $sub->busStop->name,
                            'pickup_time' => $sub->pickup_time,
                            'dropoff_stop' => $sub->dropoff_stop,
                            'dropoff_time' => $sub->dropoff_time,
                            'status' => $this->getStudentTransportStatus($sub)
                        ];
                    })
                ];
            });
    }

    /**
     * Start a route
     */
    public function startRoute(User $driver, array $data): TransportDailyLog
    {
        DB::beginTransaction();

        try {
            // Create daily log entry
            $dailyLog = TransportDailyLog::create([
                'school_id' => $driver->school_id,
                'fleet_bus_id' => $data['bus_id'],
                'transport_route_id' => $data['route_id'],
                'driver_id' => $driver->id,
                'log_date' => now()->toDateString(),
                'shift' => $this->determineShift(),
                'departure_time' => now(),
                'fuel_level_start' => $data['fuel_level'] ?? null,
                'odometer_start' => $data['odometer_reading'] ?? null,
                'safety_checklist' => $data['pre_trip_checklist'],
                'status' => 'in_progress'
            ]);

            // Update route assignment status
            BusRouteAssignment::where('driver_id', $driver->id)
                ->where('transport_route_id', $data['route_id'])
                ->where('status', 'active')
                ->update(['status' => 'in_progress']);

            // Fire route started event
            // event(new RouteStarted($dailyLog));

            DB::commit();
            return $dailyLog;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * End a route
     */
    public function endRoute(User $driver, array $data): TransportDailyLog
    {
        DB::beginTransaction();

        try {
            // Find and update the daily log
            $dailyLog = TransportDailyLog::where('driver_id', $driver->id)
                ->where('transport_route_id', $data['route_id'])
                ->where('fleet_bus_id', $data['bus_id'])
                ->whereDate('log_date', now()->toDateString())
                ->where('status', 'in_progress')
                ->firstOrFail();

            $dailyLog->update([
                'arrival_time' => now(),
                'students_picked_up' => $data['students_picked_up'],
                'students_dropped_off' => $data['students_dropped_off'],
                'fuel_level_end' => $data['fuel_level'] ?? null,
                'odometer_end' => $data['odometer_reading'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'completed'
            ]);

            // Update route assignment status
            BusRouteAssignment::where('driver_id', $driver->id)
                ->where('transport_route_id', $data['route_id'])
                ->where('status', 'in_progress')
                ->update(['status' => 'completed']);

            // Fire route completed event
            // event(new RouteCompleted($dailyLog));

            DB::commit();
            return $dailyLog;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Submit daily checklist
     */
    public function submitDailyChecklist(User $driver, array $data): void
    {
        $checklist = TransportDailyLog::create([
            'school_id' => $driver->school_id,
            'fleet_bus_id' => $data['bus_id'],
            'driver_id' => $driver->id,
            'log_date' => now()->toDateString(),
            'shift' => $this->determineShift(),
            'safety_checklist' => $data['checklist_items'],
            'notes' => $data['issues_reported'] ?? null,
            'status' => $data['safety_check_passed'] ? 'checklist_passed' : 'checklist_failed'
        ]);

                    // Fire checklist submitted event
            // event(new DailyChecklistSubmitted($checklist));
    }

    /**
     * Report an incident
     */
    public function reportIncident(User $driver, array $data): TransportIncident
    {
        $incident = TransportIncident::create([
            'school_id' => $driver->school_id,
            'fleet_bus_id' => $data['bus_id'],
            'incident_type' => $data['incident_type'],
            'severity' => $data['severity'],
            'title' => $data['title'],
            'description' => $data['description'],
            'incident_datetime' => now(),
            'incident_latitude' => $data['location']['lat'] ?? null,
            'incident_longitude' => $data['location']['lng'] ?? null,
            'affected_students' => $data['affected_students'] ?? [],
            'reported_by' => $driver->id,
            'status' => 'reported'
        ]);

                    // Fire incident reported event
            // event(new IncidentReported($incident));

        return $incident;
    }

    /**
     * Get route progress
     */
    public function getRouteProgress(User $driver, TransportRoute $route): array
    {
        $today = now()->toDateString();

        $dailyLog = TransportDailyLog::where('driver_id', $driver->id)
            ->where('transport_route_id', $route->id)
            ->whereDate('log_date', $today)
            ->first();

        $currentLocation = $this->getCurrentLocation($route);
        $stopsCompleted = $this->getCompletedStops($route, $dailyLog);
        $estimatedCompletion = $this->getEstimatedCompletionTime($route, $dailyLog);

        return [
            'route' => $route,
            'current_location' => $currentLocation,
            'progress_percentage' => $this->calculateProgressPercentage($route, $stopsCompleted),
            'stops_completed' => $stopsCompleted,
            'stops_remaining' => $this->getRemainingStops($route, $stopsCompleted),
            'estimated_completion' => $estimatedCompletion,
            'daily_log' => $dailyLog
        ];
    }

    /**
     * Get assigned bus for driver
     */
    private function getAssignedBus(User $driver): ?FleetBus
    {
        $assignment = BusRouteAssignment::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->whereDate('assigned_date', '<=', now())
            ->where(function($query) {
                $query->whereNull('valid_until')
                      ->orWhereDate('valid_until', '>=', now());
            })
            ->with('fleetBus')
            ->first();

        return $assignment?->fleetBus;
    }

    /**
     * Get recent incidents for driver's routes
     */
    private function getRecentIncidents(User $driver): Collection
    {
        $routeIds = $this->getTodayRoutes($driver)->pluck('id');

        return TransportIncident::whereIn('transport_route_id', $routeIds)
            ->orWhere('fleet_bus_id', $this->getAssignedBus($driver)?->id)
            ->orderBy('incident_datetime', 'desc')
            ->limit(5)
            ->get();
    }

    /**
     * Get today's statistics
     */
    private function getTodayStatistics(User $driver, string $date): array
    {
        $logs = TransportDailyLog::where('driver_id', $driver->id)
            ->whereDate('log_date', $date)
            ->get();

        return [
            'routes_completed' => $logs->where('status', 'completed')->count(),
            'routes_in_progress' => $logs->where('status', 'in_progress')->count(),
            'total_students_transported' => $logs->sum('students_dropped_off'),
            'total_distance' => $this->calculateTotalDistance($logs),
            'fuel_consumed' => $this->calculateFuelConsumption($logs)
        ];
    }

    /**
     * Get current route status
     */
    private function getCurrentRouteStatus(User $driver): ?string
    {
        $activeLog = TransportDailyLog::where('driver_id', $driver->id)
            ->where('status', 'in_progress')
            ->whereDate('log_date', now()->toDateString())
            ->first();

        return $activeLog ? 'in_progress' : null;
    }

    /**
     * Get next departure time
     */
    private function getNextDepartureTime(User $driver): ?string
    {
        $nextRoute = $this->getTodayRoutes($driver)
            ->where('current_status', 'scheduled')
            ->sortBy('estimated_departure')
            ->first();

        return $nextRoute?->estimated_departure;
    }

    /**
     * Get driver alerts
     */
    private function getDriverAlerts(User $driver): array
    {
        $alerts = [];

        // Check for overdue maintenance
        $bus = $this->getAssignedBus($driver);
        if ($bus && $bus->next_inspection_due && $bus->next_inspection_due->isPast()) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Bus inspection overdue',
                'priority' => 'high'
            ];
        }

        // Check for critical incidents
        $criticalIncidents = $this->getRecentIncidents($driver)
            ->where('severity', 'critical')
            ->where('status', 'reported');

        if ($criticalIncidents->isNotEmpty()) {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Critical incidents require attention',
                'priority' => 'critical'
            ];
        }

        return $alerts;
    }

    /**
     * Determine current shift
     */
    private function determineShift(): string
    {
        $hour = now()->hour;

        if ($hour >= 5 && $hour < 12) {
            return 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'afternoon';
        } else {
            return 'evening';
        }
    }

    /**
     * Calculate departure time for route
     */
    private function calculateDepartureTime(TransportRoute $route): string
    {
        // This would typically come from route schedule
        return $route->departure_time ?? '08:00';
    }

    /**
     * Calculate arrival time for route
     */
    private function calculateArrivalTime(TransportRoute $route): string
    {
        // This would typically come from route schedule
        return $route->arrival_time ?? '16:00';
    }

    /**
     * Get route status
     */
    private function getRouteStatus(BusRouteAssignment $assignment): string
    {
        $todayLog = TransportDailyLog::where('driver_id', $assignment->driver_id)
            ->where('transport_route_id', $assignment->transport_route_id)
            ->whereDate('log_date', now()->toDateString())
            ->first();

        if (!$todayLog) {
            return 'scheduled';
        }

        return $todayLog->status;
    }

    /**
     * Get student transport status
     */
    private function getStudentTransportStatus($subscription): string
    {
        // This would check actual pickup/dropoff status
        return 'scheduled';
    }

    /**
     * Get current location
     */
    private function getCurrentLocation(TransportRoute $route): ?array
    {
        $bus = $route->getCurrentBus();
        if (!$bus) return null;

        $tracking = $bus->latestTracking;
        if (!$tracking) return null;

        return [
            'latitude' => $tracking->latitude,
            'longitude' => $tracking->longitude,
            'speed' => $tracking->speed_kmh,
            'timestamp' => $tracking->tracked_at
        ];
    }

    /**
     * Get completed stops
     */
    private function getCompletedStops(TransportRoute $route, $dailyLog): int
    {
        if (!$dailyLog || $dailyLog->status !== 'completed') {
            return 0;
        }

        // This would calculate based on actual progress
        return 0;
    }

    /**
     * Get estimated completion time
     */
    private function getEstimatedCompletionTime(TransportRoute $route, $dailyLog): ?string
    {
        if (!$dailyLog || $dailyLog->status === 'completed') {
            return null;
        }

        // This would calculate based on current progress and remaining stops
        return now()->addHours(2)->format('H:i');
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgressPercentage(TransportRoute $route, int $stopsCompleted): float
    {
        $totalStops = $route->busStops->count();
        if ($totalStops === 0) return 0;

        return round(($stopsCompleted / $totalStops) * 100, 2);
    }

    /**
     * Get remaining stops
     */
    private function getRemainingStops(TransportRoute $route, int $stopsCompleted): int
    {
        $totalStops = $route->busStops->count();
        return max(0, $totalStops - $stopsCompleted);
    }

    /**
     * Calculate total distance
     */
    private function calculateTotalDistance($logs): float
    {
        return $logs->sum(function($log) {
            if ($log->odometer_start && $log->odometer_end) {
                return $log->odometer_end - $log->odometer_start;
            }
            return 0;
        });
    }

    /**
     * Calculate fuel consumption
     */
    private function calculateFuelConsumption($logs): float
    {
        return $logs->sum(function($log) {
            if ($log->fuel_level_start && $log->fuel_level_end) {
                return $log->fuel_level_start - $log->fuel_level_end;
            }
            return 0;
        });
    }
}

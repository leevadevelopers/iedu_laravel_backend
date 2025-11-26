<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use App\Models\V1\Transport\BusRouteAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FleetManagementService
{
    public function getBuses(array $filters = []): LengthAwarePaginator
    {
        $query = FleetBus::with(['currentAssignment.transportRoute', 'currentAssignment.driver'])
            ->withCount(['routeAssignments', 'transportEvents', 'incidents']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['make'])) {
            $query->where('make', 'like', '%' . $filters['make'] . '%');
        }

        if (isset($filters['model'])) {
            $query->where('model', 'like', '%' . $filters['model'] . '%');
        }

        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('license_plate', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('internal_code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('make', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('model', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('internal_code')->paginate(15);
    }

    public function createBus(array $data): FleetBus
    {
        $bus = FleetBus::create($data);

        // Log bus creation
        activity()
            ->performedOn($bus)
            ->log('Bus added to fleet');

        return $bus;
    }

    public function updateBus(FleetBus $bus, array $data): FleetBus
    {
        // If capacity is being reduced, check current occupancy
        if (isset($data['capacity']) && $data['capacity'] < $bus->current_capacity) {
            throw new \Exception('Cannot reduce capacity below current occupancy');
        }

        $bus->update($data);

        activity()
            ->performedOn($bus)
            ->log('Bus updated');

        return $bus->fresh();
    }

    public function deleteBus(FleetBus $bus): bool
    {
        // Check if bus has active assignments
        if ($bus->currentAssignment) {
            throw new \Exception('Cannot delete bus with active route assignment');
        }

        // Check if bus has recent transport events
        $hasRecentEvents = $bus->transportEvents()
            ->where('event_timestamp', '>=', now()->subDays(7))
            ->exists();

        if ($hasRecentEvents) {
            throw new \Exception('Cannot delete bus with recent transport events');
        }

        activity()
            ->performedOn($bus)
            ->log('Bus removed from fleet');

        return $bus->delete();
    }

    public function getBusDetails(FleetBus $bus): array
    {
        $bus->load([
            'currentAssignment.transportRoute.busStops',
            'currentAssignment.driver',
            'currentAssignment.assistant',
            'latestTracking',
            'incidents' => function($query) {
                $query->latest()->limit(5);
            }
        ]);

        return [
            'bus' => $bus,
            'current_location' => $bus->getLastKnownLocation(),
            'utilization_rate' => $bus->getUtilizationRate(),
            'needs_inspection' => $bus->needsInspection(),
            'maintenance_status' => $this->getMaintenanceStatus($bus),
            'performance_metrics' => $this->getBusPerformanceMetrics($bus),
            'recent_incidents' => $bus->incidents
        ];
    }

    public function assignBusToRoute(FleetBus $bus, array $data): BusRouteAssignment
    {
        // Check if bus is available
        if (!$bus->isAvailable()) {
            throw new \Exception('Bus is not available for assignment');
        }

        // Deactivate any existing assignment
        $bus->routeAssignments()
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        // Create new assignment
        $data['fleet_bus_id'] = $bus->id;
        $data['school_id'] = $data['school_id'] ?? $bus->school_id;
        $data['transport_route_id'] = $data['transport_route_id'] ?? $data['route_id'] ?? null;
        unset($data['route_id']);

        if (!$data['transport_route_id']) {
            throw new \Exception('Unable to determine transport route for assignment');
        }

        $data['status'] = $data['status'] ?? 'active';
        $assignment = BusRouteAssignment::create($data);

        activity()
            ->performedOn($assignment)
            ->log('Bus assigned to route');

        return $assignment->load(['transportRoute', 'driver', 'assistant']);
    }

    public function getAvailableBuses(): Collection
    {
        return FleetBus::available()
            ->with(['latestTracking', 'school'])
            ->orderBy('internal_code')
            ->get();
    }

    public function setBusMaintenance(FleetBus $bus): FleetBus
    {
        // Deactivate current assignment
        if ($bus->currentAssignment) {
            $bus->currentAssignment->update(['status' => 'suspended']);
        }

        $bus->update(['status' => 'maintenance']);

        activity()
            ->performedOn($bus)
            ->log('Bus set to maintenance');

        return $bus->fresh();
    }

    public function getMaintenanceReport(): array
    {
        $buses = FleetBus::with(['currentAssignment.transportRoute'])
            ->get();

        return [
            'total_fleet_size' => $buses->count(),
            'active_buses' => $buses->where('status', 'active')->count(),
            'maintenance_buses' => $buses->where('status', 'maintenance')->count(),
            'out_of_service' => $buses->where('status', 'out_of_service')->count(),
            'needing_inspection' => $buses->filter(fn($bus) => $bus->needsInspection())->count(),
            'average_age' => $this->calculateAverageFleetAge($buses),
            'utilization_rates' => $this->calculateUtilizationRates($buses),
            'upcoming_inspections' => $this->getUpcomingInspections($buses),
            'insurance_renewals' => $this->getUpcomingInsuranceRenewals($buses)
        ];
    }

    public function scheduleInspection(FleetBus $bus, array $data): void
    {
        $bus->update([
            'last_inspection_date' => $data['inspection_date'],
            'next_inspection_due' => $data['next_due_date'] ?? now()->addMonths(6)
        ]);

        activity()
            ->performedOn($bus)
            ->log('Inspection scheduled');
    }

    public function updateInsurance(FleetBus $bus, array $data): void
    {
        $bus->update([
            'insurance_expiry' => $data['expiry_date']
        ]);

        activity()
            ->performedOn($bus)
            ->log('Insurance information updated');
    }

    private function getMaintenanceStatus(FleetBus $bus): array
    {
        $status = [];

        if ($bus->needsInspection()) {
            $status[] = [
                'type' => 'inspection',
                'priority' => 'high',
                'message' => 'Inspection due: ' . $bus->next_inspection_due->format('Y-m-d')
            ];
        }

        if ($bus->insurance_expiry && $bus->insurance_expiry <= now()->addDays(30)) {
            $status[] = [
                'type' => 'insurance',
                'priority' => 'critical',
                'message' => 'Insurance expires: ' . $bus->insurance_expiry->format('Y-m-d')
            ];
        }

        return $status;
    }

    private function getBusPerformanceMetrics(FleetBus $bus): array
    {
        // Calculate metrics for the last 30 days
        $thirtyDaysAgo = now()->subDays(30);

        return [
            'trips_completed' => $this->getTripsCompleted($bus, $thirtyDaysAgo),
            'distance_traveled' => $this->getDistanceTraveled($bus, $thirtyDaysAgo),
            'fuel_consumption' => $this->getFuelConsumption($bus, $thirtyDaysAgo),
            'breakdown_incidents' => $bus->incidents()
                ->where('incident_type', 'breakdown')
                ->where('incident_datetime', '>=', $thirtyDaysAgo)
                ->count(),
            'average_delay' => $this->getAverageDelay($bus, $thirtyDaysAgo)
        ];
    }

    private function calculateAverageFleetAge(Collection $buses): float
    {
        $totalAge = $buses->sum(fn($bus) => now()->year - $bus->manufacture_year);
        return $buses->count() > 0 ? round($totalAge / $buses->count(), 1) : 0;
    }

    private function calculateUtilizationRates(Collection $buses): array
    {
        return [
            'average' => $buses->avg(fn($bus) => $bus->getUtilizationRate()),
            'high' => $buses->filter(fn($bus) => $bus->getUtilizationRate() > 80)->count(),
            'medium' => $buses->filter(fn($bus) => $bus->getUtilizationRate() >= 50 && $bus->getUtilizationRate() <= 80)->count(),
            'low' => $buses->filter(fn($bus) => $bus->getUtilizationRate() < 50)->count()
        ];
    }

    private function getUpcomingInspections(Collection $buses): Collection
    {
        return $buses->filter(fn($bus) => $bus->needsInspection())
            ->sortBy('next_inspection_due')
            ->take(10);
    }

    private function getUpcomingInsuranceRenewals(Collection $buses): Collection
    {
        return $buses->filter(fn($bus) =>
            $bus->insurance_expiry &&
            $bus->insurance_expiry <= now()->addDays(60)
        )->sortBy('insurance_expiry')->take(10);
    }

    private function getTripsCompleted(FleetBus $bus, $since): int
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->where('status', 'completed')
            ->count();
    }

    private function getDistanceTraveled(FleetBus $bus, $since): float
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->whereNotNull('odometer_start')
            ->whereNotNull('odometer_end')
            ->get()
            ->sum(fn($log) => $log->getDistanceTraveled()) ?? 0;
    }

    private function getFuelConsumption(FleetBus $bus, $since): float
    {
        return $bus->dailyLogs()
            ->where('log_date', '>=', $since)
            ->whereNotNull('fuel_level_start')
            ->whereNotNull('fuel_level_end')
            ->get()
            ->sum(fn($log) => $log->getFuelConsumed()) ?? 0;
    }

    private function getAverageDelay(FleetBus $bus, $since): float
    {
        // This would calculate from tracking data
        return 2.5; // Mock value
    }
}

<?php

namespace App\Services\V1\Transport;

use App\Models\V1\Transport\FleetBus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FleetService
{
    /**
     * Get fleet buses with filtering and pagination
     */
    public function getFleetBuses(array $filters = [], int $perPage = 15, int $page = 1, string $sortBy = 'created_at', string $sortOrder = 'desc'): array
    {
        $query = FleetBus::query();

        // Apply filters
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('license_plate', 'like', "%{$searchTerm}%")
                  ->orWhere('internal_code', 'like', "%{$searchTerm}%")
                  ->orWhere('make', 'like', "%{$searchTerm}%")
                  ->orWhere('model', 'like', "%{$searchTerm}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }

        if (!empty($filters['make'])) {
            $query->where('make', $filters['make']);
        }

        if (!empty($filters['model'])) {
            $query->where('model', 'like', "%{$filters['model']}%");
        }

        // Apply sorting
        if ($sortOrder === 'desc') {
            $query->orderBy($sortBy, 'desc');
        } else {
            $query->orderBy($sortBy, 'asc');
        }

        // Get paginated results
        $fleetBuses = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $fleetBuses->items(),
            'meta' => [
                'current_page' => $fleetBuses->currentPage(),
                'last_page' => $fleetBuses->lastPage(),
                'per_page' => $fleetBuses->perPage(),
                'total' => $fleetBuses->total(),
                'from' => $fleetBuses->firstItem(),
                'to' => $fleetBuses->lastItem()
            ]
        ];
    }

    /**
     * Create a new fleet bus
     */
    public function createFleetBus(array $data): FleetBus
    {
        return FleetBus::create($data);
    }

    /**
     * Update an existing fleet bus
     */
    public function updateFleetBus(FleetBus $fleetBus, array $data): FleetBus
    {
        $fleetBus->update($data);
        return $fleetBus->fresh();
    }

    /**
     * Delete a fleet bus (soft delete)
     */
    public function deleteFleetBus(FleetBus $fleetBus): bool
    {
        return $fleetBus->delete();
    }

    /**
     * Get fleet statistics
     */
    public function getFleetStatistics(): array
    {
        $stats = DB::table('fleet_buses')
            ->selectRaw('
                COUNT(*) as total_vehicles,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_vehicles,
                SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) as maintenance_vehicles,
                SUM(CASE WHEN status = "out_of_service" THEN 1 ELSE 0 END) as out_of_service_vehicles,
                SUM(CASE WHEN status = "retired" THEN 1 ELSE 0 END) as retired_vehicles,
                SUM(capacity) as total_capacity,
                SUM(current_capacity) as current_capacity,
                AVG(CASE WHEN fuel_consumption_per_km IS NOT NULL THEN fuel_consumption_per_km END) as avg_fuel_consumption
            ')
            ->first();

        $fuelTypeStats = DB::table('fleet_buses')
            ->select('fuel_type', DB::raw('COUNT(*) as count'))
            ->groupBy('fuel_type')
            ->get()
            ->pluck('count', 'fuel_type');

        return [
            'total_vehicles' => $stats->total_vehicles ?? 0,
            'active_vehicles' => $stats->active_vehicles ?? 0,
            'maintenance_vehicles' => $stats->maintenance_vehicles ?? 0,
            'out_of_service_vehicles' => $stats->out_of_service_vehicles ?? 0,
            'retired_vehicles' => $stats->retired_vehicles ?? 0,
            'total_capacity' => $stats->total_capacity ?? 0,
            'current_capacity' => $stats->current_capacity ?? 0,
            'available_capacity' => ($stats->total_capacity ?? 0) - ($stats->current_capacity ?? 0),
            'avg_fuel_consumption' => $stats->avg_fuel_consumption ?? 0,
            'fuel_type_distribution' => $fuelTypeStats
        ];
    }

    /**
     * Export fleet data
     */
    public function exportFleetData(array $filters = []): array
    {
        $query = FleetBus::query();

        // Apply same filters as index method
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('license_plate', 'like', "%{$searchTerm}%")
                  ->orWhere('internal_code', 'like', "%{$searchTerm}%")
                  ->orWhere('make', 'like', "%{$searchTerm}%")
                  ->orWhere('model', 'like', "%{$searchTerm}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['fuel_type'])) {
            $query->where('fuel_type', $filters['fuel_type']);
        }

        $fleetBuses = $query->with(['school'])->get();

        return $fleetBuses->map(function ($fleetBus) {
            return [
                'id' => $fleetBus->id,
                'license_plate' => $fleetBus->license_plate,
                'internal_code' => $fleetBus->internal_code,
                'make' => $fleetBus->make,
                'model' => $fleetBus->model,
                'manufacture_year' => $fleetBus->manufacture_year,
                'capacity' => $fleetBus->capacity,
                'current_capacity' => $fleetBus->current_capacity,
                'fuel_type' => $fleetBus->fuel_type,
                'fuel_consumption_per_km' => $fleetBus->fuel_consumption_per_km,
                'status' => $fleetBus->status,
                'school_name' => $fleetBus->school->name ?? 'N/A',
                'created_at' => $fleetBus->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $fleetBus->updated_at->format('Y-m-d H:i:s')
            ];
        })->toArray();
    }
}

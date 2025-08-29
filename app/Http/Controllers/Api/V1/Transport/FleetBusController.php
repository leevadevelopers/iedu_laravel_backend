<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\FleetBus;
use App\Services\V1\Transport\FleetManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FleetBusController extends Controller
{
    protected $fleetService;

    public function __construct(FleetManagementService $fleetService)
    {
        $this->fleetService = $fleetService;
        $this->middleware('auth:api');
        $this->middleware('permission:view-transport')->only(['index', 'show']);
        $this->middleware('permission:create-transport')->only(['store']);
        $this->middleware('permission:edit-transport')->only(['update']);
        $this->middleware('permission:delete-transport')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'make', 'model']);
            $buses = $this->fleetService->getBuses($filters);

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Fleet retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving fleet: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_plate' => 'required|string|max:20|unique:fleet_buses,license_plate',
            'internal_code' => 'required|string|max:20',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'manufacture_year' => 'required|integer|min:1990|max:' . (date('Y') + 1),
            'capacity' => 'required|integer|min:5|max:100',
            'fuel_type' => 'required|in:diesel,petrol,electric,hybrid',
            'fuel_consumption_per_km' => 'nullable|numeric|min:0',
            'gps_device_id' => 'nullable|string|max:100',
            'safety_features' => 'nullable|array',
            'last_inspection_date' => 'nullable|date',
            'next_inspection_due' => 'nullable|date|after:last_inspection_date',
            'insurance_expiry' => 'required|date|after:today',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bus = $this->fleetService->createBus($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $bus,
                'message' => 'Bus added to fleet successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding bus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(FleetBus $bus): JsonResponse
    {
        try {
            $busData = $this->fleetService->getBusDetails($bus);

            return response()->json([
                'success' => true,
                'data' => $busData,
                'message' => 'Bus details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, FleetBus $bus): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_plate' => 'sometimes|string|max:20|unique:fleet_buses,license_plate,' . $bus->id,
            'internal_code' => 'sometimes|string|max:20',
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'manufacture_year' => 'sometimes|integer|min:1990|max:' . (date('Y') + 1),
            'capacity' => 'sometimes|integer|min:5|max:100',
            'fuel_type' => 'sometimes|in:diesel,petrol,electric,hybrid',
            'fuel_consumption_per_km' => 'nullable|numeric|min:0',
            'gps_device_id' => 'nullable|string|max:100',
            'safety_features' => 'nullable|array',
            'last_inspection_date' => 'nullable|date',
            'next_inspection_due' => 'nullable|date',
            'insurance_expiry' => 'sometimes|date|after:today',
            'status' => 'sometimes|in:active,maintenance,out_of_service,retired',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedBus = $this->fleetService->updateBus($bus, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $updatedBus,
                'message' => 'Bus updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating bus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(FleetBus $bus): JsonResponse
    {
        try {
            $this->fleetService->deleteBus($bus);

            return response()->json([
                'success' => true,
                'message' => 'Bus removed from fleet successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing bus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assign(Request $request, FleetBus $bus): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:transport_routes,id',
            'driver_id' => 'required|exists:users,id',
            'assistant_id' => 'nullable|exists:users,id',
            'assigned_date' => 'required|date|after_or_equal:today',
            'valid_until' => 'nullable|date|after:assigned_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $assignment = $this->fleetService->assignBusToRoute($bus, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $assignment,
                'message' => 'Bus assigned to route successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning bus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAvailable(): JsonResponse
    {
        try {
            $buses = $this->fleetService->getAvailableBuses();

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Available buses retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available buses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function maintenance(FleetBus $bus): JsonResponse
    {
        try {
            $this->fleetService->setBusMaintenance($bus);

            return response()->json([
                'success' => true,
                'message' => 'Bus set to maintenance mode successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error setting maintenance: ' . $e->getMessage()
            ], 500);
        }
    }

    public function maintenanceReport(): JsonResponse
    {
        try {
            $report = $this->fleetService->getMaintenanceReport();

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Maintenance report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating maintenance report: ' . $e->getMessage()
            ], 500);
        }
    }
}

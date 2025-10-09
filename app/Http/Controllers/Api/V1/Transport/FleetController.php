<?php

namespace App\Http\Controllers\Api\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\FleetBus;
use App\Services\V1\Transport\FleetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class FleetController extends Controller
{
    protected $fleetService;

    public function __construct(FleetService $fleetService)
    {
        $this->fleetService = $fleetService;
    }

    /**
     * Get all fleet buses with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'fuel_type', 'make', 'model']);
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            $result = $this->fleetService->getFleetBuses($filters, $perPage, $page, $sortBy, $sortOrder);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar frota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created fleet bus
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'school_id' => 'required|exists:schools,id',
                'license_plate' => 'required|string|max:20|unique:fleet_buses,license_plate',
                'internal_code' => 'required|string|max:20',
                'make' => 'required|string|max:255',
                'model' => 'required|string|max:255',
                'manufacture_year' => 'required|numeric|min:1900|max:2025',
                'capacity' => 'required|integer|min:1|max:100',
                'current_capacity' => 'integer|min:0',
                'fuel_type' => 'required|in:diesel,petrol,electric,hybrid',
                'fuel_consumption_per_km' => 'nullable|numeric|min:0',
                'gps_device_id' => 'nullable|string|max:255',
                'safety_features' => 'nullable|array',
                'last_inspection_date' => 'nullable|date',
                'next_inspection_due' => 'nullable|date',
                'insurance_expiry' => 'nullable|date',
                'status' => 'required|in:active,maintenance,out_of_service,retired',
                'notes' => 'nullable|string'
            ]);

            $fleetBus = $this->fleetService->createFleetBus($validated);

            return response()->json([
                'success' => true,
                'data' => $fleetBus,
                'message' => 'Veículo criado com sucesso'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific fleet bus
     */
    public function show(FleetBus $fleetBus): JsonResponse
    {
        try {
            $fleetBus->load(['school']);

            return response()->json([
                'success' => true,
                'data' => $fleetBus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a fleet bus
     */
    public function update(Request $request, FleetBus $fleetBus): JsonResponse
    {
        try {
            $validated = $request->validate([
                'school_id' => 'sometimes|exists:schools,id',
                'license_plate' => 'sometimes|string|max:20|unique:fleet_buses,license_plate,' . $fleetBus->id,
                'internal_code' => 'sometimes|string|max:20',
                'make' => 'sometimes|string|max:255',
                'model' => 'sometimes|string|max:255',
                'manufacture_year' => 'sometimes|numeric|min:1900|max:2025',
                'capacity' => 'sometimes|integer|min:1|max:100',
                'current_capacity' => 'sometimes|integer|min:0',
                'fuel_type' => 'sometimes|in:diesel,petrol,electric,hybrid',
                'fuel_consumption_per_km' => 'nullable|numeric|min:0',
                'gps_device_id' => 'nullable|string|max:255',
                'safety_features' => 'nullable|array',
                'last_inspection_date' => 'nullable|date',
                'next_inspection_due' => 'nullable|date',
                'insurance_expiry' => 'nullable|date',
                'status' => 'sometimes|in:active,maintenance,out_of_service,retired',
                'notes' => 'nullable|string'
            ]);

            $fleetBus = $this->fleetService->updateFleetBus($fleetBus, $validated);

            return response()->json([
                'success' => true,
                'data' => $fleetBus,
                'message' => 'Veículo atualizado com sucesso'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a fleet bus
     */
    public function destroy(FleetBus $fleetBus): JsonResponse
    {
        try {
            $this->fleetService->deleteFleetBus($fleetBus);

            return response()->json([
                'success' => true,
                'message' => 'Veículo excluído com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir veículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fleet statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->fleetService->getFleetStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export fleet data
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'fuel_type']);
            
            // This would typically return a file download
            $exportData = $this->fleetService->exportFleetData($filters);

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Dados exportados com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar dados',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

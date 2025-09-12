<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\TransportIncident;
use App\Models\V1\Transport\FleetBus;
use App\Services\V1\Transport\IncidentManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportIncidentController extends Controller
{
    protected $incidentService;

    public function __construct(IncidentManagementService $incidentService)
    {
        $this->incidentService = $incidentService;
        $this->middleware('auth:api');
        // $this->middleware('permission:view-transport')->only(['index', 'show']);
        // $this->middleware('permission:create-transport')->only(['store']);
        // $this->middleware('permission:edit-transport')->only(['update', 'assign', 'resolve']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'severity', 'incident_type', 'bus_id', 'search']);
            $incidents = $this->incidentService->getIncidents($filters);

            return response()->json([
                'success' => true,
                'data' => $incidents,
                'message' => 'Incidents retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving incidents: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'transport_route_id' => 'nullable|exists:transport_routes,id',
            'incident_type' => 'required|in:breakdown,accident,delay,behavioral,medical,other',
            'severity' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'incident_datetime' => 'nullable|date',
            'incident_latitude' => 'nullable|numeric|between:-90,90',
            'incident_longitude' => 'nullable|numeric|between:-180,180',
            'affected_students' => 'nullable|array',
            'witnesses' => 'nullable|array',
            'immediate_action_taken' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $incident = $this->incidentService->createIncident($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $incident,
                'message' => 'Incident reported successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(TransportIncident $incident): JsonResponse
    {
        try {
            $incidentData = $this->incidentService->getIncidentDetails($incident);

            return response()->json([
                'success' => true,
                'data' => $incidentData,
                'message' => 'Incident retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, TransportIncident $incident): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'incident_type' => 'sometimes|in:breakdown,accident,delay,behavioral,medical,other',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'affected_students' => 'nullable|array',
            'witnesses' => 'nullable|array',
            'immediate_action_taken' => 'nullable|string',
            'status' => 'sometimes|in:reported,investigating,resolved,closed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedIncident = $this->incidentService->updateIncident($incident, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $updatedIncident,
                'message' => 'Incident updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assign(Request $request, TransportIncident $incident): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->incidentService->assignIncident($incident, $request->assigned_to);

            return response()->json([
                'success' => true,
                'message' => 'Incident assigned successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resolve(Request $request, TransportIncident $incident): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->incidentService->resolveIncident($incident, $request->resolution_notes);

            return response()->json([
                'success' => true,
                'message' => 'Incident resolved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resolving incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusIncidents(FleetBus $bus): JsonResponse
    {
        try {
            $incidents = $bus->incidents()
                ->with(['reportedBy', 'assignedTo'])
                ->orderBy('incident_datetime', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $incidents,
                'message' => 'Bus incidents retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving incidents: ' . $e->getMessage()
            ], 500);
        }
    }

    public function emergencyAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'emergency_type' => 'required|in:breakdown,accident,delay,behavioral,medical,emergency',
            'location' => 'required|array',
            'location.lat' => 'required|numeric',
            'location.lng' => 'required|numeric',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->incidentService->handleEmergencyAlert($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Emergency alert processed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing emergency alert: ' . $e->getMessage()
            ], 500);
        }
    }
}

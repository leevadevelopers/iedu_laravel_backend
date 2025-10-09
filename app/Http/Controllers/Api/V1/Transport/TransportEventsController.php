<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\StudentTransportEvent;
use App\Services\V1\Transport\StudentTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportEventsController extends Controller
{
    protected $studentTransportService;

    public function __construct(StudentTransportService $studentTransportService)
    {
        $this->studentTransportService = $studentTransportService;
        $this->middleware('auth:api');
        $this->middleware('permission:view-transport')->only(['index', 'show']);
        $this->middleware('permission:create-transport')->only(['store']);
        $this->middleware('permission:edit-transport')->only(['update']);
        $this->middleware('permission:delete-transport')->only(['destroy']);
    }

    /**
     * Get all transport events with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'event_type', 'validation_method', 'student_id', 
                'fleet_bus_id', 'transport_route_id', 'date_from', 'date_to',
                'is_automated', 'page', 'per_page', 'sort_by', 'sort_order'
            ]);

            $events = $this->studentTransportService->getTransportEvents($filters);

            return response()->json([
                'success' => true,
                'data' => $events->items(),
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
                'message' => 'Transport events retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving transport events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific transport event
     */
    public function show(StudentTransportEvent $event): JsonResponse
    {
        try {
            $event->load([
                'student:id,name,student_id',
                'fleetBus:id,license_plate,internal_code,make,model',
                'busStop:id,name,code,address',
                'transportRoute:id,name,code',
                'recordedBy:id,name,email'
            ]);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Transport event retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving transport event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new transport event
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'bus_stop_id' => 'required|exists:bus_stops,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'event_type' => 'required|in:check_in,check_out,no_show,early_exit',
            'event_timestamp' => 'required|date',
            'validation_method' => 'required|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string|max:100',
            'recorded_by' => 'required|exists:users,id',
            'event_latitude' => 'nullable|numeric|between:-90,90',
            'event_longitude' => 'nullable|numeric|between:-180,180',
            'is_automated' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event = StudentTransportEvent::create($validator->validated());
            $event->load([
                'student:id,name,student_id',
                'fleetBus:id,license_plate,internal_code,make,model',
                'busStop:id,name,code,address',
                'transportRoute:id,name,code',
                'recordedBy:id,name,email'
            ]);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Transport event created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating transport event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a transport event
     */
    public function update(Request $request, StudentTransportEvent $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'sometimes|in:check_in,check_out,no_show,early_exit',
            'event_timestamp' => 'sometimes|date',
            'validation_method' => 'sometimes|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string|max:100',
            'event_latitude' => 'nullable|numeric|between:-90,90',
            'event_longitude' => 'nullable|numeric|between:-180,180',
            'is_automated' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event->update($validator->validated());
            $event->load([
                'student:id,name,student_id',
                'fleetBus:id,license_plate,internal_code,make,model',
                'busStop:id,name,code,address',
                'transportRoute:id,name,code',
                'recordedBy:id,name,email'
            ]);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Transport event updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating transport event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a transport event
     */
    public function destroy(StudentTransportEvent $event): JsonResponse
    {
        try {
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transport event deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting transport event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transport events statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'school_id']);
            $stats = $this->studentTransportService->getTransportEventStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Transport events statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent transport events
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $events = $this->studentTransportService->getRecentEvents($limit);

            return response()->json([
                'success' => true,
                'data' => $events,
                'message' => 'Recent transport events retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving recent events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export transport events
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'event_type', 'validation_method', 'student_id', 
                'fleet_bus_id', 'transport_route_id', 'date_from', 'date_to',
                'is_automated'
            ]);

            $exportData = $this->studentTransportService->exportTransportEvents($filters);

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Transport events exported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting transport events: ' . $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\FleetBus;
use App\Services\V1\Transport\TransportTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportTrackingController extends Controller
{
    protected $trackingService;

    public function __construct(TransportTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
        $this->middleware('auth:api');
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'fleet_bus_id' => 'required|exists:fleet_buses,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'required|numeric|min:0|max:120',
            'heading' => 'nullable|integer|min:0|max:359',
            'altitude' => 'nullable|numeric',
            'tracked_at' => 'nullable|date',
            'status' => 'nullable|string|in:departed,in_transit,at_stop,arrived',
            'current_stop_id' => 'nullable|exists:bus_stops,id',
            'next_stop_id' => 'nullable|exists:bus_stops,id',
            'eta_minutes' => 'nullable|integer|min:0',
            'raw_gps_data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tracking = $this->trackingService->updateLocation($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $tracking,
                'message' => 'Location updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating location: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusLocation(FleetBus $bus): JsonResponse
    {
        try {
            $location = $this->trackingService->getCurrentLocation($bus);

            return response()->json([
                'success' => true,
                'data' => $location,
                'message' => 'Bus location retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving location: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRouteProgress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:transport_routes,id',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $progress = $this->trackingService->getRouteProgress($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $progress,
                'message' => 'Route progress retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving progress: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getEta(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
            'stop_id' => 'required|exists:bus_stops,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $eta = $this->trackingService->calculateEta($request->bus_id, $request->stop_id);

            return response()->json([
                'success' => true,
                'data' => ['eta_minutes' => $eta],
                'message' => 'ETA calculated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating ETA: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getActiveBuses(): JsonResponse
    {
        try {
            $buses = $this->trackingService->getActiveBusesWithLocation();

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Active buses retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active buses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTrackingHistory(FleetBus $bus, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'hours' => 'nullable|integer|min:1|max:24'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $history = $this->trackingService->getTrackingHistory($bus, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Tracking history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateGeofence(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stop_id' => 'required|exists:bus_stops,id',
            'radius_meters' => 'required|integer|min:10|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $geofence = $this->trackingService->generateGeofence($request->stop_id, $request->radius_meters);

            return response()->json([
                'success' => true,
                'data' => $geofence,
                'message' => 'Geofence generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating geofence: ' . $e->getMessage()
            ], 500);
        }
    }

    public function webhookGpsUpdate(Request $request): JsonResponse
    {
        try {
            // This would typically process GPS device webhook data
            // For now, just acknowledge receipt
            return response()->json([
                'success' => true,
                'message' => 'GPS update received'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing GPS update: ' . $e->getMessage()
            ], 500);
        }
    }
}

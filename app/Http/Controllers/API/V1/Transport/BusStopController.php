<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\BusStop;
use App\Models\V1\Transport\TransportRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusStopController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = BusStop::with(['transportRoute'])->orderBy('name');

            if ($request->has('route_id')) {
                $query->where('transport_route_id', $request->route_id);
            }

            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('address', 'like', '%' . $request->search . '%')
                      ->orWhere('code', 'like', '%' . $request->search . '%');
                });
            }

            $stops = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $stops,
                'message' => 'Bus stops retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bus stops: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'required|exists:schools,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20',
            'address' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'scheduled_arrival_time' => 'required|date_format:H:i',
            'scheduled_departure_time' => 'required|date_format:H:i|after:scheduled_arrival_time',
            'estimated_wait_minutes' => 'nullable|integer|min:1|max:30',
            'is_pickup_point' => 'boolean',
            'is_dropoff_point' => 'boolean',
            'landmarks' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $route = TransportRoute::findOrFail($request->transport_route_id);

            // Set stop order as next in sequence
            $maxOrder = $route->busStops()->max('stop_order') ?? 0;
            $data = array_merge($validator->validated(), [
                'stop_order' => $maxOrder + 1
            ]);

            $stop = BusStop::create($data);

            return response()->json([
                'success' => true,
                'data' => $stop->load('transportRoute'),
                'message' => 'Bus stop created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating bus stop: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(BusStop $stop): JsonResponse
    {
        try {
            $stop->load([
                'transportRoute',
                'pickupSubscriptions.student',
                'dropoffSubscriptions.student'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'stop' => $stop,
                    'pickup_students_count' => $stop->pickupSubscriptions()->count(),
                    'dropoff_students_count' => $stop->dropoffSubscriptions()->count(),
                    'total_usage' => $stop->getActiveStudentCount()
                ],
                'message' => 'Bus stop retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bus stop: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, BusStop $stop): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_id' => 'sometimes|exists:schools,id',
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'scheduled_arrival_time' => 'sometimes|date_format:H:i',
            'scheduled_departure_time' => 'sometimes|date_format:H:i',
            'estimated_wait_minutes' => 'nullable|integer|min:1|max:30',
            'is_pickup_point' => 'sometimes|boolean',
            'is_dropoff_point' => 'sometimes|boolean',
            'landmarks' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,temporary'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $stop->update($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $stop->fresh()->load('transportRoute'),
                'message' => 'Bus stop updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating bus stop: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(BusStop $stop): JsonResponse
    {
        try {
            // Check if stop has active subscriptions
            $hasActiveSubscriptions = $stop->pickupSubscriptions()->where('status', 'active')->exists() ||
                                     $stop->dropoffSubscriptions()->where('status', 'active')->exists();

            if ($hasActiveSubscriptions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete bus stop with active student subscriptions'
                ], 422);
            }

            $stop->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bus stop deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting bus stop: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByRoute(TransportRoute $route): JsonResponse
    {
        try {
            $stops = $route->busStops()
                ->orderBy('stop_order')
                ->withCount(['pickupSubscriptions', 'dropoffSubscriptions'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stops,
                'message' => 'Route stops retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving route stops: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stops' => 'required|array|min:1',
            'stops.*.id' => 'required|exists:bus_stops,id',
            'stops.*.order' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->stops as $stopData) {
                BusStop::where('id', $stopData['id'])
                    ->update(['stop_order' => $stopData['order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bus stops reordered successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reordering stops: ' . $e->getMessage()
            ], 500);
        }
    }
}

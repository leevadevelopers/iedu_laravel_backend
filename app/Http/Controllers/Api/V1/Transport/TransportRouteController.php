<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\TransportRoute;
use App\Services\V1\Transport\TransportRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportRouteController extends Controller
{
    protected $transportRouteService;

    public function __construct(TransportRouteService $transportRouteService)
    {
        $this->transportRouteService = $transportRouteService;
        $this->middleware('auth:api');
        $this->middleware('permission:view-transport')->only(['index', 'show']);
        $this->middleware('permission:create-transport')->only(['store']);
        $this->middleware('permission:edit-transport')->only(['update']);
        $this->middleware('permission:delete-transport')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'shift', 'search']);
            $routes = $this->transportRouteService->getRoutes($filters);

            return response()->json([
                'success' => true,
                'data' => $routes,
                'message' => 'Routes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving routes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:transport_routes,code',
            'description' => 'nullable|string',
            'waypoints' => 'required|array',
            'waypoints.*.lat' => 'required|numeric|between:-90,90',
            'waypoints.*.lng' => 'required|numeric|between:-180,180',
            'departure_time' => 'required|date_format:H:i',
            'arrival_time' => 'required|date_format:H:i|after:departure_time',
            'total_distance_km' => 'required|numeric|min:0',
            'shift' => 'required|in:morning,afternoon,both',
            'operating_days' => 'required|array|min:1',
            'operating_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $route = $this->transportRouteService->createRoute($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $route->load(['busStops']),
                'message' => 'Route created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(TransportRoute $route): JsonResponse
    {
        try {
            $routeData = $this->transportRouteService->getRouteDetails($route);

            return response()->json([
                'success' => true,
                'data' => $routeData,
                'message' => 'Route retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, TransportRoute $route): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'waypoints' => 'sometimes|array',
            'waypoints.*.lat' => 'required_with:waypoints|numeric|between:-90,90',
            'waypoints.*.lng' => 'required_with:waypoints|numeric|between:-180,180',
            'departure_time' => 'sometimes|date_format:H:i',
            'arrival_time' => 'sometimes|date_format:H:i',
            'total_distance_km' => 'sometimes|numeric|min:0',
            'shift' => 'sometimes|in:morning,afternoon,both',
            'operating_days' => 'sometimes|array|min:1',
            'operating_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'status' => 'sometimes|in:active,inactive,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updatedRoute = $this->transportRouteService->updateRoute($route, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $updatedRoute->load(['busStops']),
                'message' => 'Route updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(TransportRoute $route): JsonResponse
    {
        try {
            $this->transportRouteService->deleteRoute($route);

            return response()->json([
                'success' => true,
                'message' => 'Route deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getActiveRoutes(): JsonResponse
    {
        try {
            $routes = $this->transportRouteService->getActiveRoutes();

            return response()->json([
                'success' => true,
                'data' => $routes,
                'message' => 'Active routes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active routes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function optimize(TransportRoute $route): JsonResponse
    {
        try {
            $optimizedRoute = $this->transportRouteService->optimizeRoute($route);

            return response()->json([
                'success' => true,
                'data' => $optimizedRoute,
                'message' => 'Route optimized successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error optimizing route: ' . $e->getMessage()
            ], 500);
        }
    }
}

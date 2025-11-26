<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\TransportRoute;
use App\Services\V1\Transport\DriverPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverPortalController extends Controller
{
    protected $driverService;

    public function __construct(DriverPortalService $driverService)
    {
        $this->driverService = $driverService;
        $this->middleware('auth:api');
    }

    public function dashboard(): JsonResponse
    {
        try {
            $dashboard = $this->driverService->getDashboard(auth('api')->user());

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Driver dashboard retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTodayRoutes(): JsonResponse
    {
        try {
            $routes = $this->driverService->getTodayRoutes(auth('api')->user());

            return response()->json([
                'success' => true,
                'data' => $routes,
                'message' => 'Today\'s routes retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving routes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAssignedStudents(): JsonResponse
    {
        try {
            $students = $this->driverService->getAssignedStudents(auth('api')->user());

            return response()->json([
                'success' => true,
                'data' => $students,
                'message' => 'Assigned students retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving students: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:transport_routes,id',
            'bus_id' => 'required|exists:fleet_buses,id',
            'odometer_reading' => 'nullable|integer',
            'fuel_level' => 'nullable|numeric|min:0|max:100',
            'pre_trip_checklist' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $routeLog = $this->driverService->startRoute(auth('api')->user(), $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $routeLog,
                'message' => 'Route started successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error starting route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function endRoute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'route_id' => 'required|exists:transport_routes,id',
            'bus_id' => 'required|exists:fleet_buses,id',
            'odometer_reading' => 'nullable|integer',
            'fuel_level' => 'nullable|numeric|min:0|max:100',
            'students_picked_up' => 'required|integer|min:0',
            'students_dropped_off' => 'required|integer|min:0',
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
            $routeLog = $this->driverService->endRoute(auth('api')->user(), $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $routeLog,
                'message' => 'Route completed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error ending route: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitDailyChecklist(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
            'checklist_items' => 'required|array',
            'safety_check_passed' => 'required|boolean',
            'issues_reported' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->driverService->submitDailyChecklist(auth('api')->user(), $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Daily checklist submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting checklist: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reportIncident(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
            'incident_type' => 'required|in:breakdown,accident,delay,behavioral,medical,other',
            'severity' => 'required|in:low,medium,high,critical',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|array',
            'location.lat' => 'nullable|numeric',
            'location.lng' => 'nullable|numeric',
            'affected_students' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $incident = $this->driverService->reportIncident(auth('api')->user(), $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $incident,
                'message' => 'Incident reported successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error reporting incident: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRouteProgress(TransportRoute $route): JsonResponse
    {
        try {
            $progress = $this->driverService->getRouteProgress(auth('api')->user(), $route);

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
}

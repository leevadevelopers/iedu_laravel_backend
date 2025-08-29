#!/bin/bash

# Transport Module - Controllers Generator
echo "🎮 Creating Transport Module Controllers..."

# 1. TransportRouteController
cat > app/Http/Controllers/API/V1/Transport/TransportRouteController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\TransportRoute;
use App\Services\Transport\TransportRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportRouteController extends Controller
{
    protected $transportRouteService;

    public function __construct(TransportRouteService $transportRouteService)
    {
        $this->transportRouteService = $transportRouteService;
        $this->middleware('auth:sanctum');
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
EOF

# 2. FleetBusController
cat > app/Http/Controllers/API/V1/Transport/FleetBusController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\FleetBus;
use App\Services\Transport\FleetManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FleetBusController extends Controller
{
    protected $fleetService;

    public function __construct(FleetManagementService $fleetService)
    {
        $this->fleetService = $fleetService;
        $this->middleware('auth:sanctum');
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
EOF

# 3. StudentTransportController
cat > app/Http/Controllers/API/V1/Transport/StudentTransportController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\StudentTransportSubscription;
use App\Services\Transport\StudentTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentTransportController extends Controller
{
    protected $studentTransportService;

    public function __construct(StudentTransportService $studentTransportService)
    {
        $this->studentTransportService = $studentTransportService;
        $this->middleware('auth:sanctum');
        $this->middleware('permission:view-students')->only(['index', 'show']);
        $this->middleware('permission:create-transport')->only(['subscribe', 'checkin', 'checkout']);
        $this->middleware('permission:edit-transport')->only(['update']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['route_id', 'status', 'search']);
            $subscriptions = $this->studentTransportService->getSubscriptions($filters);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Student subscriptions retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'pickup_stop_id' => 'required|exists:bus_stops,id',
            'dropoff_stop_id' => 'required|exists:bus_stops,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'subscription_type' => 'required|in:daily,weekly,monthly,term',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'monthly_fee' => 'nullable|numeric|min:0',
            'authorized_parents' => 'nullable|array',
            'authorized_parents.*' => 'exists:users,id',
            'special_needs' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = $this->studentTransportService->createSubscription($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $subscription->load(['student', 'pickupStop', 'dropoffStop', 'transportRoute']),
                'message' => 'Student subscribed to transport successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            $subscriptionData = $this->studentTransportService->getSubscriptionDetails($subscription);

            return response()->json([
                'success' => true,
                'data' => $subscriptionData,
                'message' => 'Subscription details retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'bus_id' => 'required|exists:fleet_buses,id',
            'stop_id' => 'required|exists:bus_stops,id',
            'validation_method' => 'required|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
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
            $event = $this->studentTransportService->recordCheckin($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Student checked in successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording checkin: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'bus_id' => 'required|exists:fleet_buses,id',
            'stop_id' => 'required|exists:bus_stops,id',
            'validation_method' => 'required|in:qr_code,rfid,manual,facial_recognition',
            'validation_data' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
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
            $event = $this->studentTransportService->recordCheckout($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Student checked out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error recording checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStudentHistory(Student $student): JsonResponse
    {
        try {
            $history = $this->studentTransportService->getStudentHistory($student);

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Student transport history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateQrCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code is required',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->studentTransportService->validateQrCode($request->qr_code);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'QR Code validated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR Code: ' . $e->getMessage()
            ], 400);
        }
    }

    public function generateQrCode(StudentTransportSubscription $subscription): JsonResponse
    {
        try {
            $qrCodeImage = $this->studentTransportService->generateQrCode($subscription);

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_code' => $subscription->qr_code,
                    'image' => $qrCodeImage
                ],
                'message' => 'QR Code generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating QR Code: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusRoster(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
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
            $roster = $this->studentTransportService->getBusRoster($validator->validated());

            return response()->json([
                'success' => true,
                'data' => $roster,
                'message' => 'Bus roster retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving roster: ' . $e->getMessage()
            ], 500);
        }
    }
}
EOF

# 4. TransportTrackingController
cat > app/Http/Controllers/API/V1/Transport/TransportTrackingController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\Transport\FleetBus;
use App\Services\Transport\TransportTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransportTrackingController extends Controller
{
    protected $trackingService;

    public function __construct(TransportTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
        $this->middleware('auth:sanctum');
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_id' => 'required|exists:fleet_buses,id',
            'route_id' => 'required|exists:transport_routes,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'required|numeric|min:0|max:120',
            'heading' => 'nullable|integer|min:0|max:359',
            'altitude' => 'nullable|numeric',
            'status' => 'nullable|string|in:departed,in_transit,at_stop,arrived',
            'current_stop_id' => 'nullable|exists:bus_stops,id',
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
}
EOF

# 5. ParentPortalController
cat > app/Http/Controllers/API/V1/Transport/ParentPortalController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Transport;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Services\Transport\ParentPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParentPortalController extends Controller
{
    protected $parentPortalService;

    public function __construct(ParentPortalService $parentPortalService)
    {
        $this->parentPortalService = $parentPortalService;
        $this->middleware('auth:sanctum');
        $this->middleware('permission:view-own-students');
    }

    public function dashboard(): JsonResponse
    {
        try {
            $dashboard = $this->parentPortalService->getDashboard(auth()->user());

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Dashboard data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStudentStatus(Student $student): JsonResponse
    {
        // Verify parent has access to this student
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to student'
            ], 403);
        }

        try {
            $status = $this->parentPortalService->getStudentTransportStatus($student);

            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'Student status retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getBusLocation(Student $student): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $location = $this->parentPortalService->getStudentBusLocation($student);

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

    public function getTransportHistory(Student $student, Request $request): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $history = $this->parentPortalService->getTransportHistory($student, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => 'Transport history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'required|boolean',
            'sms_notifications' => 'required|boolean',
            'push_notifications' => 'required|boolean',
            'whatsapp_notifications' => 'required|boolean',
            'notification_types' => 'required|array',
            'notification_types.*' => 'in:check_in,check_out,delay,incident,route_change'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $preferences = $this->parentPortalService->updateNotificationPreferences(
                auth()->user(),
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $preferences,
                'message' => 'Notification preferences updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getNotifications(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,sent,delivered,failed,read',
            'type' => 'nullable|in:check_in,check_out,delay,incident,route_change',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notifications = $this->parentPortalService->getNotifications(
                auth()->user(),
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'message' => 'Notifications retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markNotificationRead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|exists:transport_notifications,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $this->parentPortalService->markNotificationAsRead($request->notification_id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error marking notification: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRouteMap(Student $student): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $routeMap = $this->parentPortalService->getRouteMap($student);

            return response()->json([
                'success' => true,
                'data' => $routeMap,
                'message' => 'Route map retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving route map: ' . $e->getMessage()
            ], 500);
        }
    }

    public function requestStopChange(Student $student, Request $request): JsonResponse
    {
        if (!$this->parentPortalService->hasAccessToStudent(auth()->user(), $student)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_pickup_stop_id' => 'nullable|exists:bus_stops,id',
            'new_dropoff_stop_id' => 'nullable|exists:bus_stops,id',
            'reason' => 'required|string|max:500',
            'effective_date' => 'required|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $request = $this->parentPortalService->requestStopChange($student, $validator->validated());

            return response()->json([
                'success' => true,
                'data' => $request,
                'message' => 'Stop change request submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error submitting request: ' . $e->getMessage()
            ], 500);
        }
    }
}
EOF

echo "✅ Transport module controllers created successfully!"
echo "📝 Controllers include authentication, validation, and proper error handling."

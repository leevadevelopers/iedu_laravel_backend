#!/bin/bash

# Transport Module - Routes Generator
echo "🛣️ Creating Transport Module Routes..."

# 1. Main Transport Routes File
cat > routes/modules/transport/transport.php << 'EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Transport\TransportRouteController;
use App\Http\Controllers\API\V1\Transport\FleetBusController;
use App\Http\Controllers\API\V1\Transport\StudentTransportController;
use App\Http\Controllers\API\V1\Transport\TransportTrackingController;
use App\Http\Controllers\API\V1\Transport\ParentPortalController;
use App\Http\Controllers\API\V1\Transport\BusStopController;
use App\Http\Controllers\API\V1\Transport\TransportIncidentController;
use App\Http\Controllers\API\V1\Transport\TransportReportsController;
use App\Http\Controllers\API\V1\Transport\DriverPortalController;

/*
|--------------------------------------------------------------------------
| Transport Module Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the transport management system including
| fleet management, student transport, tracking, and parent portal.
|
*/

Route::middleware(['auth:api', 'multi-tenant'])->prefix('transport')->name('transport.')->group(function () {

    // ==========================================
    // TRANSPORT ROUTES MANAGEMENT
    // ==========================================
    Route::prefix('routes')->name('routes.')->group(function () {
        Route::get('/', [TransportRouteController::class, 'index'])->name('index');
        Route::post('/', [TransportRouteController::class, 'store'])->name('store');
        Route::get('/active', [TransportRouteController::class, 'getActiveRoutes'])->name('active');
        Route::get('/{route}', [TransportRouteController::class, 'show'])->name('show');
        Route::put('/{route}', [TransportRouteController::class, 'update'])->name('update');
        Route::delete('/{route}', [TransportRouteController::class, 'destroy'])->name('destroy');
        Route::post('/{route}/optimize', [TransportRouteController::class, 'optimize'])->name('optimize');
    });

    // ==========================================
    // BUS STOPS MANAGEMENT
    // ==========================================
    Route::prefix('stops')->name('stops.')->group(function () {
        Route::get('/', [BusStopController::class, 'index'])->name('index');
        Route::post('/', [BusStopController::class, 'store'])->name('store');
        Route::get('/route/{route}', [BusStopController::class, 'getByRoute'])->name('by-route');
        Route::get('/{stop}', [BusStopController::class, 'show'])->name('show');
        Route::put('/{stop}', [BusStopController::class, 'update'])->name('update');
        Route::delete('/{stop}', [BusStopController::class, 'destroy'])->name('destroy');
        Route::post('/{stop}/reorder', [BusStopController::class, 'reorder'])->name('reorder');
    });

    // ==========================================
    // FLEET MANAGEMENT
    // ==========================================
    Route::prefix('fleet')->name('fleet.')->group(function () {
        Route::get('/', [FleetBusController::class, 'index'])->name('index');
        Route::post('/', [FleetBusController::class, 'store'])->name('store');
        Route::get('/available', [FleetBusController::class, 'getAvailable'])->name('available');
        Route::get('/maintenance-report', [FleetBusController::class, 'maintenanceReport'])->name('maintenance-report');
        Route::get('/{bus}', [FleetBusController::class, 'show'])->name('show');
        Route::put('/{bus}', [FleetBusController::class, 'update'])->name('update');
        Route::delete('/{bus}', [FleetBusController::class, 'destroy'])->name('destroy');
        Route::post('/{bus}/assign', [FleetBusController::class, 'assign'])->name('assign');
        Route::post('/{bus}/maintenance', [FleetBusController::class, 'maintenance'])->name('maintenance');
    });

    // ==========================================
    // STUDENT TRANSPORT MANAGEMENT
    // ==========================================
    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', [StudentTransportController::class, 'index'])->name('index');
        Route::post('/subscribe', [StudentTransportController::class, 'subscribe'])->name('subscribe');
        Route::get('/{subscription}', [StudentTransportController::class, 'show'])->name('show');
        Route::put('/{subscription}', [StudentTransportController::class, 'update'])->name('update');

        // Student Events (Check-in/Check-out)
        Route::post('/checkin', [StudentTransportController::class, 'checkin'])->name('checkin');
        Route::post('/checkout', [StudentTransportController::class, 'checkout'])->name('checkout');
        Route::post('/validate-qr', [StudentTransportController::class, 'validateQrCode'])->name('validate-qr');
        Route::get('/{subscription}/qr-code', [StudentTransportController::class, 'generateQrCode'])->name('qr-code');

        // Student History & Reports
        Route::get('/student/{student}/history', [StudentTransportController::class, 'getStudentHistory'])->name('history');
        Route::get('/roster', [StudentTransportController::class, 'getBusRoster'])->name('roster');
    });

    // ==========================================
    // GPS TRACKING & MONITORING
    // ==========================================
    Route::prefix('tracking')->name('tracking.')->group(function () {
        Route::post('/location', [TransportTrackingController::class, 'updateLocation'])->name('update-location');
        Route::get('/active-buses', [TransportTrackingController::class, 'getActiveBuses'])->name('active-buses');
        Route::get('/bus/{bus}/location', [TransportTrackingController::class, 'getBusLocation'])->name('bus-location');
        Route::get('/route-progress', [TransportTrackingController::class, 'getRouteProgress'])->name('route-progress');
        Route::get('/eta', [TransportTrackingController::class, 'getEta'])->name('eta');
        Route::get('/bus/{bus}/history', [TransportTrackingController::class, 'getTrackingHistory'])->name('history');
        Route::post('/geofence', [TransportTrackingController::class, 'generateGeofence'])->name('geofence');
    });

    // ==========================================
    // INCIDENTS MANAGEMENT
    // ==========================================
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [TransportIncidentController::class, 'index'])->name('index');
        Route::post('/', [TransportIncidentController::class, 'store'])->name('store');
        Route::get('/{incident}', [TransportIncidentController::class, 'show'])->name('show');
        Route::put('/{incident}', [TransportIncidentController::class, 'update'])->name('update');
        Route::post('/{incident}/assign', [TransportIncidentController::class, 'assign'])->name('assign');
        Route::post('/{incident}/resolve', [TransportIncidentController::class, 'resolve'])->name('resolve');
        Route::get('/bus/{bus}/incidents', [TransportIncidentController::class, 'getBusIncidents'])->name('bus-incidents');
    });

    // ==========================================
    // REPORTS & ANALYTICS
    // ==========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/dashboard', [TransportReportsController::class, 'dashboard'])->name('dashboard');
        Route::get('/attendance', [TransportReportsController::class, 'attendanceReport'])->name('attendance');
        Route::get('/performance', [TransportReportsController::class, 'performanceReport'])->name('performance');
        Route::get('/financial', [TransportReportsController::class, 'financialReport'])->name('financial');
        Route::get('/safety', [TransportReportsController::class, 'safetyReport'])->name('safety');
        Route::get('/utilization', [TransportReportsController::class, 'utilizationReport'])->name('utilization');
        Route::post('/custom', [TransportReportsController::class, 'generateCustomReport'])->name('custom');
    });
});

// ==========================================
// PARENT PORTAL ROUTES
// ==========================================
Route::middleware(['auth:api', 'multi-tenant'])->prefix('parent/transport')->name('parent.transport.')->group(function () {
    Route::get('/dashboard', [ParentPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/student/{student}/status', [ParentPortalController::class, 'getStudentStatus'])->name('student-status');
    Route::get('/student/{student}/location', [ParentPortalController::class, 'getBusLocation'])->name('bus-location');
    Route::get('/student/{student}/history', [ParentPortalController::class, 'getTransportHistory'])->name('history');
    Route::get('/student/{student}/route-map', [ParentPortalController::class, 'getRouteMap'])->name('route-map');
    Route::post('/student/{student}/request-change', [ParentPortalController::class, 'requestStopChange'])->name('request-change');

    // Notifications
    Route::get('/notifications', [ParentPortalController::class, 'getNotifications'])->name('notifications');
    Route::post('/notifications/preferences', [ParentPortalController::class, 'updateNotificationPreferences'])->name('notification-preferences');
    Route::post('/notifications/mark-read', [ParentPortalController::class, 'markNotificationRead'])->name('mark-read');
});

// ==========================================
// DRIVER/ASSISTANT PORTAL ROUTES
// ==========================================
Route::middleware(['auth:api', 'multi-tenant'])->prefix('driver/transport')->name('driver.transport.')->group(function () {
    Route::get('/dashboard', [DriverPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/today-routes', [DriverPortalController::class, 'getTodayRoutes'])->name('today-routes');
    Route::get('/assigned-students', [DriverPortalController::class, 'getAssignedStudents'])->name('assigned-students');
    Route::post('/start-route', [DriverPortalController::class, 'startRoute'])->name('start-route');
    Route::post('/end-route', [DriverPortalController::class, 'endRoute'])->name('end-route');
    Route::post('/daily-checklist', [DriverPortalController::class, 'submitDailyChecklist'])->name('daily-checklist');
    Route::post('/report-incident', [DriverPortalController::class, 'reportIncident'])->name('report-incident');
    Route::get('/route/{route}/progress', [DriverPortalController::class, 'getRouteProgress'])->name('route-progress');
});

// ==========================================
// PUBLIC API ROUTES (No Authentication)
// ==========================================
Route::prefix('public/transport')->name('public.transport.')->group(function () {
    // Webhook endpoints for GPS tracking devices
    Route::post('/webhook/gps-update', [TransportTrackingController::class, 'webhookGpsUpdate'])->name('webhook-gps');

    // Public route information for integration
    Route::get('/routes/public-info', [TransportRouteController::class, 'getPublicRouteInfo'])->name('public-routes');

    // Emergency contact endpoints
    Route::post('/emergency-alert', [TransportIncidentController::class, 'emergencyAlert'])->name('emergency-alert');
});
EOF

# 2. BusStopController
cat > app/Http/Controllers/API/V1/Transport/BusStopController.php << 'EOF'
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
        $this->middleware('permission:view-transport')->only(['index', 'show', 'getByRoute']);
        $this->middleware('permission:create-transport')->only(['store']);
        $this->middleware('permission:edit-transport')->only(['update', 'reorder']);
        $this->middleware('permission:delete-transport')->only(['destroy']);
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
EOF

# 3. TransportIncidentController
cat > app/Http/Controllers/API/V1/Transport/TransportIncidentController.php << 'EOF'
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
        $this->middleware('permission:view-transport')->only(['index', 'show']);
        $this->middleware('permission:create-transport')->only(['store']);
        $this->middleware('permission:edit-transport')->only(['update', 'assign', 'resolve']);
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
            'bus_id' => 'required|exists:fleet_buses,id',
            'emergency_type' => 'required|string',
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
EOF

# 4. DriverPortalController
cat > app/Http/Controllers/API/V1/Transport/DriverPortalController.php << 'EOF'
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
        $this->middleware('role:driver|assistant');
    }

    public function dashboard(): JsonResponse
    {
        try {
            $dashboard = $this->driverService->getDashboard(auth()->user());

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
            $routes = $this->driverService->getTodayRoutes(auth()->user());

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
            $students = $this->driverService->getAssignedStudents(auth()->user());

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
            $routeLog = $this->driverService->startRoute(auth()->user(), $validator->validated());

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
            $routeLog = $this->driverService->endRoute(auth()->user(), $validator->validated());

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
            $this->driverService->submitDailyChecklist(auth()->user(), $validator->validated());

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
            $incident = $this->driverService->reportIncident(auth()->user(), $validator->validated());

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
            $progress = $this->driverService->getRouteProgress(auth()->user(), $route);

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
EOF

# 5. Add routes to main route file
cat > routes/transport_routes_registration.php << 'EOF'
<?php

// Add this to your main routes/web.php or routes/api.php file

// Transport Module Routes
Route::middleware(['api', 'throttle:api'])->group(function () {
    require_once __DIR__ . '/transport/transport.php';
});

// Additional route model bindings for transport
Route::bind('route', function ($value) {
    return \App\Models\Transport\TransportRoute::findOrFail($value);
});

Route::bind('bus', function ($value) {
    return \App\Models\Transport\FleetBus::findOrFail($value);
});

Route::bind('stop', function ($value) {
    return \App\Models\Transport\BusStop::findOrFail($value);
});

Route::bind('subscription', function ($value) {
    return \App\Models\Transport\StudentTransportSubscription::findOrFail($value);
});

Route::bind('incident', function ($value) {
    return \App\Models\Transport\TransportIncident::findOrFail($value);
});
EOF

echo "✅ Transport module routes created successfully!"
echo "📝 Routes include complete API endpoints for all transport operations."
echo ""
echo "🔗 Generated routes include:"
echo "   - Transport routes management"
echo "   - Fleet bus management"
echo "   - Student transport operations"
echo "   - GPS tracking and monitoring"
echo "   - Parent portal endpoints"
echo "   - Driver portal endpoints"
echo "   - Incident management"
echo "   - Reports and analytics"

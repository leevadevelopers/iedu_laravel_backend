<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Transport\FleetController;
use App\Http\Controllers\API\V1\Transport\TransportRouteController;
use App\Http\Controllers\API\V1\Transport\FleetBusController;
use App\Http\Controllers\API\V1\Transport\StudentTransportController;
use App\Http\Controllers\API\V1\Transport\TransportSubscriptionController;
use App\Http\Controllers\API\V1\Transport\TransportTrackingController;
use App\Http\Controllers\API\V1\Transport\ParentPortalController;
use App\Http\Controllers\API\V1\Transport\BusStopController;
use App\Http\Controllers\API\V1\Transport\TransportIncidentController;
use App\Http\Controllers\API\V1\Transport\DriverPortalController;
use App\Http\Controllers\API\V1\Transport\TransportEventsController;

/*
|--------------------------------------------------------------------------
| Transport Module Routes
|--------------------------------------------------------------------------
|
| Here are the routes for the transport management system including
| fleet management, student transport, tracking, and parent portal.
|
*/

// Temporary route for testing fleet API without authentication
Route::get('/api/transport/fleet/test', function () {
    $vehicles = App\Models\V1\Transport\FleetBus::select('id', 'license_plate', 'make', 'model', 'capacity', 'status')->get();
    return response()->json([
        'success' => true,
        'data' => $vehicles,
        'meta' => [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => count($vehicles),
            'total' => count($vehicles),
            'from' => 1,
            'to' => count($vehicles)
        ]
    ]);
});

Route::middleware(['auth:api'])->prefix('transport')->name('transport.')->group(function () {

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
        // New API routes
        Route::get('/', [FleetController::class, 'index'])->name('index');
        Route::post('/', [FleetController::class, 'store'])->name('store');
        Route::get('/statistics', [FleetController::class, 'statistics'])->name('statistics');
        Route::get('/export', [FleetController::class, 'export'])->name('export');
        Route::get('/{fleet}', [FleetController::class, 'show'])->name('show');
        Route::put('/{fleet}', [FleetController::class, 'update'])->name('update');
        Route::delete('/{fleet}', [FleetController::class, 'destroy'])->name('destroy');

        // Legacy routes (keeping for compatibility)
        Route::get('/available', [FleetBusController::class, 'getAvailable'])->name('available');
        Route::get('/maintenance-report', [FleetBusController::class, 'maintenanceReport'])->name('maintenance-report');
        Route::get('/{bus}', [FleetBusController::class, 'show'])->name('legacy.show');
        Route::put('/{bus}', [FleetBusController::class, 'update'])->name('legacy.update');
        Route::delete('/{bus}', [FleetBusController::class, 'destroy'])->name('legacy.destroy');
        Route::post('/{bus}/assign', [FleetBusController::class, 'assign'])->name('assign');
        Route::post('/{bus}/maintenance', [FleetBusController::class, 'maintenance'])->name('maintenance');
    });

    // ==========================================
    // STUDENT TRANSPORT MANAGEMENT
    // ==========================================
    Route::prefix('students')->name('students.')->group(function () {

        // Student History & Reports
        Route::get('/student/{student}/history', [StudentTransportController::class, 'getStudentHistory'])->name('history');
        Route::get('/student/{student}/subscription-status', [StudentTransportController::class, 'checkSubscriptionStatus'])->name('subscription-status');
        Route::get('/roster', [StudentTransportController::class, 'getBusRoster'])->name('roster');


        Route::get('/', [StudentTransportController::class, 'index'])->name('index');
        Route::post('/subscribe', [StudentTransportController::class, 'subscribe'])->name('subscribe');
        Route::get('/{subscription}', [StudentTransportController::class, 'show'])->name('show');
        Route::put('/{subscription}', [StudentTransportController::class, 'update'])->name('update');

        // Student Events (Check-in/Check-out)
        Route::post('/checkin', [StudentTransportController::class, 'checkin'])->name('checkin');
        Route::post('/checkout', [StudentTransportController::class, 'checkout'])->name('checkout');
        Route::post('/validate-qr', [StudentTransportController::class, 'validateQrCode'])->name('validate-qr');
        Route::get('/{subscription}/qr-code', [StudentTransportController::class, 'generateQrCode'])->name('qr-code');
    });

    // ==========================================
    // TRANSPORT SUBSCRIPTIONS MANAGEMENT
    // ==========================================
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        // Reports & Analytics (must come before parameterized routes)
        Route::get('/statistics', [TransportSubscriptionController::class, 'getStatistics'])->name('statistics');
        Route::get('/expiring', [TransportSubscriptionController::class, 'getExpiring'])->name('expiring');
        Route::get('/by-route', [TransportSubscriptionController::class, 'getByRoute'])->name('by-route');
        Route::get('/student/{student}', [TransportSubscriptionController::class, 'getByStudent'])->name('by-student');

        // CRUD Operations
        Route::get('/', [TransportSubscriptionController::class, 'index'])->name('index');
        Route::post('/', [TransportSubscriptionController::class, 'store'])->name('store');

        // Parameterized routes (must come after specific routes)
        Route::get('/{subscription}', [TransportSubscriptionController::class, 'show'])->name('show');
        Route::put('/{subscription}', [TransportSubscriptionController::class, 'update'])->name('update');
        Route::delete('/{subscription}', [TransportSubscriptionController::class, 'destroy'])->name('destroy');

        // Status Management
        Route::post('/{subscription}/activate', [TransportSubscriptionController::class, 'activate'])->name('activate');
        Route::post('/{subscription}/suspend', [TransportSubscriptionController::class, 'suspend'])->name('suspend');
        Route::post('/{subscription}/cancel', [TransportSubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/{subscription}/renew', [TransportSubscriptionController::class, 'renew'])->name('renew');

        // QR Code Management
        Route::get('/{subscription}/qr-code', [TransportSubscriptionController::class, 'generateQrCode'])->name('qr-code');
    });

    // ==========================================
    // GPS TRACKING & MONITORING
    // ==========================================
    Route::prefix('tracking')->name('tracking.')->group(function () {
        Route::get('/eta', [TransportTrackingController::class, 'getEta'])->name('eta');
        Route::post('/location', [TransportTrackingController::class, 'updateLocation'])->name('update-location');
        Route::get('/active-buses', [TransportTrackingController::class, 'getActiveBuses'])->name('active-buses');
        Route::get('/bus/{bus}/location', [TransportTrackingController::class, 'getBusLocation'])->name('bus-location');
        Route::get('/route-progress', [TransportTrackingController::class, 'getRouteProgress'])->name('route-progress');
        Route::get('/bus/{bus}/history', [TransportTrackingController::class, 'getTrackingHistory'])->name('history');
        Route::post('/geofence', [TransportTrackingController::class, 'generateGeofence'])->name('geofence');
    });

    // ==========================================
    // TRANSPORT EVENTS MANAGEMENT
    // ==========================================
    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/', [TransportEventsController::class, 'index'])->name('index');
        Route::post('/', [TransportEventsController::class, 'store'])->name('store');
        Route::get('/statistics', [TransportEventsController::class, 'statistics'])->name('statistics');
        Route::get('/recent', [TransportEventsController::class, 'recent'])->name('recent');
        Route::get('/export', [TransportEventsController::class, 'export'])->name('export');
        Route::get('/{event}', [TransportEventsController::class, 'show'])->name('show');
        Route::put('/{event}', [TransportEventsController::class, 'update'])->name('update');
        Route::delete('/{event}', [TransportEventsController::class, 'destroy'])->name('destroy');
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
    // Route::prefix('reports')->name('reports.')->group(function () {
    //     Route::get('/dashboard', [TransportReportsController::class, 'dashboard'])->name('dashboard');
    //     Route::get('/attendance', [TransportReportsController::class, 'attendanceReport'])->name('attendance');
    //     Route::get('/performance', [TransportReportsController::class, 'performanceReport'])->name('performance');
    //     Route::get('/financial', [TransportReportsController::class, 'financialReport'])->name('financial');
    //     Route::get('/safety', [TransportReportsController::class, 'safetyReport'])->name('safety');
    //     Route::get('/utilization', [TransportReportsController::class, 'utilizationReport'])->name('utilization');
    //     Route::post('/custom', [TransportReportsController::class, 'generateCustomReport'])->name('custom');
    // });
});

// ==========================================
// PARENT PORTAL ROUTES
// ==========================================
Route::middleware(['auth:api'])->prefix('parent/transport')->name('parent.transport.')->group(function () {
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

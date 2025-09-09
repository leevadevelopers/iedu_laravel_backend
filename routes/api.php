<?php


use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\Route;

//v1 group
Route::prefix('v1')->group(function () {
    require __DIR__ . '/modules/auth.php';
    require __DIR__ . '/modules/users.php';
    require __DIR__ . '/modules/forms.php';
    require __DIR__ . '/modules/notification.php';
    require __DIR__ . '/modules/tenant.php';
    require __DIR__ . '/modules/school.php';
    require __DIR__ . '/modules/students.php';
    require __DIR__ . '/modules/roles_permission/roles.php';
});

// Transport Module Routes
Route::middleware(['api', 'throttle:api'])->group(function () {
    require_once __DIR__ . '/modules/transport/transport.php';
});

    



// File upload routes
Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::prefix('v1/files')->group(function () {
        Route::post('/upload', [\App\Http\Controllers\API\V1\FileUploadController::class, 'upload']);
        Route::post('/upload-multiple', [\App\Http\Controllers\API\V1\FileUploadController::class, 'uploadMultiple']);
        Route::delete('/delete', [\App\Http\Controllers\API\V1\FileUploadController::class, 'delete']);
        Route::get('/info', [\App\Http\Controllers\API\V1\FileUploadController::class, 'info']);
    });
});

// Additional route model bindings for transport
Route::bind('route', function ($value) {
    return \App\Models\V1\Transport\TransportRoute::findOrFail($value);
});

Route::bind('bus', function ($value) {
    return \App\Models\V1\Transport\FleetBus::findOrFail($value);
});

Route::bind('stop', function ($value) {
    return \App\Models\V1\Transport\BusStop::findOrFail($value);
});

Route::bind('subscription', function ($value) {
    return \App\Models\V1\Transport\StudentTransportSubscription::findOrFail($value);
});

Route::bind('incident', function ($value) {
    return \App\Models\V1\Transport\TransportIncident::findOrFail($value);
});

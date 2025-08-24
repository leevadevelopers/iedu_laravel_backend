<?php


use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\Route;


require __DIR__ . '/modules/auth.php';
require __DIR__ . '/modules/users.php';
require __DIR__ . '/modules/forms.php';
require __DIR__ . '/modules/notification.php';
require __DIR__ . '/modules/tenant.php';
require __DIR__ . '/modules/school.php';

// Permission management routes (protected)
Route::middleware(['auth:api', 'tenant'])->prefix('permissions')->group(function () {
    Route::get('/', [\App\Http\Controllers\API\PermissionController::class, 'index']);
    Route::get('/matrix', [\App\Http\Controllers\API\PermissionController::class, 'matrix']);

    // Role management routes
    Route::get('/roles', [\App\Http\Controllers\API\PermissionController::class, 'roles']);
    Route::post('/roles', [\App\Http\Controllers\API\PermissionController::class, 'store']);
    Route::get('/roles/{role}', [\App\Http\Controllers\API\PermissionController::class, 'show']);
    Route::put('/roles/{role}', [\App\Http\Controllers\API\PermissionController::class, 'update']);
    Route::delete('/roles/{role}', [\App\Http\Controllers\API\PermissionController::class, 'destroy']);
    Route::get('/roles/{role}/permissions', [\App\Http\Controllers\API\PermissionController::class, 'rolePermissions']);
    Route::put('/roles/{role}/permissions', [\App\Http\Controllers\API\PermissionController::class, 'updateRolePermissions']);

    // User role assignment routes
    Route::post('/users/assign-role', [\App\Http\Controllers\API\PermissionController::class, 'assignRoleToUser']);
    Route::delete('/users/remove-role', [\App\Http\Controllers\API\PermissionController::class, 'removeRoleFromUser']);

    // User permissions routes
    Route::get('/user', [\App\Http\Controllers\API\PermissionController::class, 'userPermissions']);
    Route::put('/user', [\App\Http\Controllers\API\PermissionController::class, 'updateUserPermissions']);
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


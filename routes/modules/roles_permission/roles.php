<?php

use Illuminate\Support\Facades\Route;

// ==========================================
// ROLES AND PERMISSIONS MANAGEMENT
// ==========================================

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

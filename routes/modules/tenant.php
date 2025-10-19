<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TenantController;

// Tenant routes (protected)
Route::prefix('tenants')->middleware('auth:api')->group(function () {

    Route::post('/', [TenantController::class, 'store']);
    Route::post('switch', [TenantController::class, 'switch']);

    Route::middleware(['tenant'])->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::get('current', [TenantController::class, 'show']);
        Route::get('users', [TenantController::class, 'users']);
        Route::post('users', [TenantController::class, 'addUser']);
        Route::delete('users/{userId}', [TenantController::class, 'removeUser']);
        Route::put('users/{userId}/role', [TenantController::class, 'updateUserRole']);
        Route::get('settings', [TenantController::class, 'settings']);
        Route::put('settings', [TenantController::class, 'updateSettings']);
        Route::get('branding', [TenantController::class, 'branding']);
        Route::put('branding', [TenantController::class, 'updateBranding']);
        
        // Invitations routes
        //uuxee
        Route::get('invitations', [TenantController::class, 'invitations']);
        Route::post('invitations', [TenantController::class, 'sendInvitation']);
        Route::delete('invitations/{invitation}', [TenantController::class, 'cancelInvitation']);
        Route::delete('invitations/{invitation}/permanent', [TenantController::class, 'deleteInvitation']);
        
        // Public invitation routes (no authentication required)
        Route::post('invitations/validate', [TenantController::class, 'validateInvitation']);
        Route::post('invitations/accept', [TenantController::class, 'acceptInvitation']);
        
        // Debug route for invitation link testing
        Route::post('invitations/debug-link', [TenantController::class, 'debugInvitationLink']);
    });
});



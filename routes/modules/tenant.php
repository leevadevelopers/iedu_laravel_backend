<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TenantController;
use App\Http\Controllers\API\V1\Tenant\TenantController as V1TenantController;

// Tenant routes (protected) - V1 API
Route::prefix('tenants')->middleware('auth:api')->group(function () {
    // Routes that don't require tenant context (superadmin can use these)
    Route::get('/', [V1TenantController::class, 'index']);
    Route::post('/', [V1TenantController::class, 'store']);
    Route::post('switch', [V1TenantController::class, 'switch']);
    Route::get('{id}', [V1TenantController::class, 'showById']);
    Route::put('{id}', [V1TenantController::class, 'update']);
    Route::delete('{id}', [V1TenantController::class, 'destroy']);

    // Routes that require tenant context
    Route::middleware(['tenant'])->group(function () {
        Route::get('current', [V1TenantController::class, 'show']);

        Route::middleware(['permission:teams.view|teams.manage|users.view|users.manage'])->group(function () {
            Route::get('users', [V1TenantController::class, 'users']);
        });

        Route::middleware(['permission:teams.manage|users.manage'])->group(function () {
            Route::post('users', [V1TenantController::class, 'addUser']);
        });

        Route::middleware(['permission:tenants.manage_settings|teams.manage|users.manage'])->group(function () {
            Route::get('settings', [V1TenantController::class, 'settings']);
            Route::put('settings', [V1TenantController::class, 'updateSettings']);
            Route::get('branding', [V1TenantController::class, 'branding']);
            Route::put('branding', [V1TenantController::class, 'updateBranding']);
        });

        // Legacy routes (using old controller for backward compatibility)
        Route::middleware(['permission:teams.remove|teams.manage|users.manage'])->group(function () {
            Route::delete('users/{userId}', [TenantController::class, 'removeUser']);
        });
        Route::middleware(['permission:teams.manage|users.manage'])->group(function () {
            Route::put('users/{userId}/role', [TenantController::class, 'updateUserRole']);
        });

        // Invitations routes (admin)
        Route::middleware(['permission:teams.view|teams.manage'])->group(function () {
            Route::get('invitations', [TenantController::class, 'invitations']);
        });
        Route::middleware(['permission:teams.invite|teams.manage'])->group(function () {
            Route::post('invitations', [TenantController::class, 'sendInvitation']);
        });
        Route::middleware(['permission:teams.manage|users.manage'])->group(function () {
            Route::delete('invitations/{invitation}', [TenantController::class, 'cancelInvitation']);
            Route::delete('invitations/{invitation}/permanent', [TenantController::class, 'deleteInvitation']);
            Route::post('invitations/debug-link', [TenantController::class, 'debugInvitationLink']);
        });

        // Invitation token flow (authenticated user completing invite)
        Route::post('invitations/validate', [TenantController::class, 'validateInvitation']);
        Route::post('invitations/accept', [TenantController::class, 'acceptInvitation']);
    });
});



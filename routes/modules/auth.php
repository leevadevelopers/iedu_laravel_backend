<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Auth\PasswordController;
use App\Http\Controllers\API\V1\Auth\ProfileController;


// Auth routes with v1 prefix to be consistent with other modules
Route::prefix('auth')->group(function () {
    Route::post('sign-in', [AuthController::class, 'login']);
    Route::post('sign-up', [AuthController::class, 'register']);
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('reset-password', [PasswordController::class, 'reset']);
    Route::post('validate-token', [AuthController::class, 'validateToken']);

    Route::middleware('auth:api')->group(function () {
        Route::post('sign-out', [AuthController::class, 'logout']);
        Route::post('logout', [AuthController::class, 'logout']); // Add both sign-out and logout for compatibility
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']); // Only requires auth, not tenant
        Route::post('change-password', [PasswordController::class, 'change']);

        // Profile routes
        Route::get('profile', [ProfileController::class, 'getProfile']);
        Route::put('profile', [ProfileController::class, 'updateProfile']);
        Route::post('profile', [ProfileController::class, 'updateProfile']); // Also support POST
        Route::post('profile/photo', [ProfileController::class, 'uploadProfilePhoto']);
        Route::delete('profile/photo', [ProfileController::class, 'deleteProfilePhoto']);
    });
});

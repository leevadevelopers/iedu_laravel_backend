<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\SuperAdmin\SuperAdminDashboardController;
use App\Http\Middleware\SuperAdminMiddleware;

Route::middleware(['auth:api', SuperAdminMiddleware::class])->group(function () {
    Route::prefix('super-admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'dashboard'])->name('super-admin.dashboard');
        
        // Cache management
        Route::post('/dashboard/clear-cache', [SuperAdminDashboardController::class, 'clearCache'])->name('super-admin.dashboard.clear-cache');
    });
});


<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\School\SchoolOwnerDashboardController;
use App\Http\Middleware\SchoolOwnerMiddleware;

Route::middleware(['auth:api', 'tenant', SchoolOwnerMiddleware::class])->group(function () {
    Route::prefix('school-owner')->group(function () {
        // Dashboard
        Route::get('/dashboard', [SchoolOwnerDashboardController::class, 'dashboard'])->name('school-owner.dashboard');
        
        // Cache management
        Route::post('/dashboard/clear-cache', [SchoolOwnerDashboardController::class, 'clearCache'])->name('school-owner.dashboard.clear-cache');
    });
});


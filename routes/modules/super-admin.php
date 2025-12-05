<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\API\V1\SuperAdmin\SuperAdminReportsController;
use App\Http\Middleware\SuperAdminMiddleware;

Route::middleware(['auth:api', SuperAdminMiddleware::class])->group(function () {
    Route::prefix('super-admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'dashboard'])->name('super-admin.dashboard');
        
        // Cache management
        Route::post('/dashboard/clear-cache', [SuperAdminDashboardController::class, 'clearCache'])->name('super-admin.dashboard.clear-cache');
        
        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            // School Reports
            Route::get('/schools', [SuperAdminReportsController::class, 'schools'])->name('schools');
            Route::get('/schools/export', [SuperAdminReportsController::class, 'exportSchools'])->name('schools.export');
            
            // User Reports
            Route::get('/users', [SuperAdminReportsController::class, 'users'])->name('users');
            Route::get('/users/export', [SuperAdminReportsController::class, 'exportUsers'])->name('users.export');
            
            // Financial Reports
            Route::get('/financial', [SuperAdminReportsController::class, 'financial'])->name('financial');
            Route::get('/financial/export', [SuperAdminReportsController::class, 'exportFinancial'])->name('financial.export');
            
            // System Performance
            Route::get('/system-performance', [SuperAdminReportsController::class, 'systemPerformance'])->name('system-performance');
            
            // Export Data
            Route::post('/export', [SuperAdminReportsController::class, 'exportData'])->name('export');
        });
    });
});


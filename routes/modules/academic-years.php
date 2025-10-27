<?php

use App\Http\Controllers\API\V1\School\AcademicYearController;
use App\Http\Controllers\API\V1\School\AcademicTermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Academic Years API Routes
|--------------------------------------------------------------------------
|
| These routes handle all academic years and terms management functionality.
| All routes are school-scoped and require authentication.
|
*/

Route::middleware(['auth:api', 'tenant'])->group(function () {
    
    // Academic Years Management
    Route::prefix('academic-years')->name('academic-years.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [AcademicYearController::class, 'index'])->name('index');
        Route::post('/', [AcademicYearController::class, 'store'])->name('store');
        Route::get('/{id}', [AcademicYearController::class, 'show'])->name('show');
        Route::put('/{id}', [AcademicYearController::class, 'update'])->name('update');
        Route::delete('/{id}', [AcademicYearController::class, 'destroy'])->name('destroy');
        
        // Academic year queries
        Route::get('by-school/{schoolId}', [AcademicYearController::class, 'getBySchool'])
            ->name('by-school');
        Route::get('current/{schoolId}', [AcademicYearController::class, 'getCurrent'])
            ->name('current');
        Route::get('current', [AcademicYearController::class, 'getCurrent'])
            ->name('current-general');

        // Academic year management
        Route::post('{id}/set-as-current', [AcademicYearController::class, 'setAsCurrent'])
            ->name('set-as-current');
        Route::get('{id}/calendar', [AcademicYearController::class, 'getCalendar'])
            ->name('calendar');

        // Bulk operations
        Route::post('bulk-create', [AcademicYearController::class, 'bulkCreate'])
            ->name('bulk-create');

        // Statistics and trends
        Route::get('statistics', [AcademicYearController::class, 'getStatistics'])
            ->name('statistics');
        Route::get('trends', [AcademicYearController::class, 'getTrends'])
            ->name('trends');

        // Search operations
        Route::get('search/by-year', [AcademicYearController::class, 'searchByYear'])
            ->name('search-by-year');
    });

    // Academic Terms Management
    Route::prefix('academic-terms')->name('academic-terms.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [AcademicTermController::class, 'index'])->name('index');
        Route::post('/', [AcademicTermController::class, 'store'])->name('store');
        Route::get('/{id}', [AcademicTermController::class, 'show'])->name('show');
        Route::put('/{id}', [AcademicTermController::class, 'update'])->name('update');
        Route::delete('/{id}', [AcademicTermController::class, 'destroy'])->name('destroy');
        
        // Academic term queries
        Route::get('by-academic-year/{academicYearId}', [AcademicTermController::class, 'getByAcademicYear'])
            ->name('by-academic-year');
        Route::get('current/{schoolId}', [AcademicTermController::class, 'getCurrent'])
            ->name('current');
        Route::get('current', [AcademicTermController::class, 'getCurrent'])
            ->name('current-general');

        // Academic term management
        Route::post('{id}/set-as-current', [AcademicTermController::class, 'setAsCurrent'])
            ->name('set-as-current');
    });
});

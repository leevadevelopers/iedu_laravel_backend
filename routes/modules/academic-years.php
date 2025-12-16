<?php

use App\Http\Controllers\API\V1\School\AcademicYearController;
use App\Http\Controllers\API\V1\School\AcademicTermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Academic Years and Terms API Routes
|--------------------------------------------------------------------------
|
| These routes handle all academic years and terms management functionality.
| All routes are school-scoped and require authentication with tenant middleware.
|
*/

Route::middleware(['auth:api', 'tenant'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Academic Year Management Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('academic-years')->name('academic-years.')->group(function () {
        // Statistics and trends - must be defined BEFORE apiResource to avoid model binding conflicts
        Route::get('statistics', [AcademicYearController::class, 'getStatistics'])
            ->name('statistics');
        Route::get('trends', [AcademicYearController::class, 'getTrends'])
            ->name('trends');

        // Search operations - must be defined BEFORE apiResource
        Route::get('search/by-year', [AcademicYearController::class, 'searchByYear'])
            ->name('search-by-year');

        // Bulk operations - must be defined BEFORE apiResource
        Route::post('bulk-create', [AcademicYearController::class, 'bulkCreate'])
            ->name('bulk-create');

        // Academic year queries - must be defined BEFORE apiResource
        Route::get('by-school/{schoolId}', [AcademicYearController::class, 'getBySchool'])
            ->name('by-school');
        Route::get('current/{schoolId}', [AcademicYearController::class, 'getCurrent'])
            ->name('current');
        Route::get('current', [AcademicYearController::class, 'getCurrent'])
            ->name('current-general');
    });

    // apiResource must be defined AFTER specific routes to avoid conflicts
    Route::apiResource('academic-years', AcademicYearController::class);

    Route::prefix('academic-years')->name('academic-years.')->group(function () {
        // Academic year management - these use model binding so must be AFTER apiResource
        Route::post('{academicYear}/set-as-current', [AcademicYearController::class, 'setAsCurrent'])
            ->name('set-as-current');
        Route::get('{academicYear}/calendar', [AcademicYearController::class, 'getCalendar'])
            ->name('calendar');
        Route::get('{academicYear}/terms', [AcademicYearController::class, 'getTerms'])
            ->name('terms');
    });

    /*
    |--------------------------------------------------------------------------
    | Academic Term Management Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('academic-terms')->name('academic-terms.')->group(function () {
        // Statistics and trends - must be defined BEFORE apiResource
        Route::get('statistics', [AcademicTermController::class, 'getStatistics'])
            ->name('statistics');
        Route::get('trends', [AcademicTermController::class, 'getTrends'])
            ->name('trends');

        // Bulk operations - must be defined BEFORE apiResource
        Route::post('bulk-create', [AcademicTermController::class, 'bulkCreate'])
            ->name('bulk-create');

        // Academic term queries - must be defined BEFORE apiResource
        Route::get('by-academic-year/{academicYearId}', [AcademicTermController::class, 'getByAcademicYear'])
            ->name('by-academic-year');
        Route::get('current/{schoolId}', [AcademicTermController::class, 'getCurrent'])
            ->name('current');
        Route::get('current', [AcademicTermController::class, 'getCurrent'])
            ->name('current-general');
    });

    // apiResource must be defined AFTER specific routes to avoid conflicts
    Route::apiResource('academic-terms', AcademicTermController::class);

    Route::prefix('academic-terms')->name('academic-terms.')->group(function () {
        // Academic term management - these use model binding so must be AFTER apiResource
        Route::post('{academicTerm}/set-as-current', [AcademicTermController::class, 'setAsCurrent'])
            ->name('set-as-current');
        Route::get('{academicTerm}/calendar', [AcademicTermController::class, 'getCalendar'])
            ->name('calendar');
    });
});

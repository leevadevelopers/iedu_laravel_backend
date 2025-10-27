 <?php

use App\Http\Controllers\API\V1\School\SchoolController;
use Illuminate\Support\Facades\Route;

// School Management with Form Engine Integration

/*
    |--------------------------------------------------------------------------
    | School Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api'])->prefix('schools')->group(function () {
        // Basic CRUD operations
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{school}', [SchoolController::class, 'show']);
        Route::put('/{id}', [SchoolController::class, 'update']);
        Route::delete('/{id}', [SchoolController::class, 'destroy']);

        // School-specific operations
        Route::get('/{school}/dashboard', [SchoolController::class, 'getDashboard']);
        Route::get('/{school}/statistics', [SchoolController::class, 'getStatistics']);
        Route::get('/{school}/students', [SchoolController::class, 'getStudents']);
        Route::get('/{school}/academic-years', [SchoolController::class, 'getAcademicYears']);
        Route::post('/{school}/set-current-academic-year', [SchoolController::class, 'setCurrentAcademicYear']);
        Route::get('/{school}/performance-metrics', [SchoolController::class, 'getPerformanceMetrics']);

        // Form Template Management
        Route::get('/form-templates', [SchoolController::class, 'getFormTemplates']);
        Route::get('/form-templates/{template}', [SchoolController::class, 'getFormTemplate']);
        Route::post('/form-templates', [SchoolController::class, 'createFormTemplate']);
        Route::put('/form-templates/{template}', [SchoolController::class, 'updateFormTemplate']);
        Route::delete('/form-templates/{template}', [SchoolController::class, 'deleteFormTemplate']);
        Route::post('/form-templates/{template}/duplicate', [SchoolController::class, 'duplicateFormTemplate']);

        // Form Engine Operations
        Route::post('/{school}/forms/submit', [SchoolController::class, 'processFormSubmission']);
        Route::get('/{school}/forms', [SchoolController::class, 'getFormInstances']);
        Route::get('/{school}/forms/{instanceId}', [SchoolController::class, 'getFormInstance']);
        Route::put('/{school}/forms/{instanceId}/status', [SchoolController::class, 'updateFormInstanceStatus']);
        Route::get('/{school}/forms/analytics', [SchoolController::class, 'getFormAnalytics']);
    });

    /*
    |--------------------------------------------------------------------------
    | Academic Year Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api'])->group(function () {
        // Academic year queries (must be before apiResource to avoid conflicts)
        Route::get('academic-years/search/by-year', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'searchByYear'])
            ->name('academic-years.search-by-year');
        Route::get('academic-years/by-school/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getBySchool'])
            ->name('academic-years.by-school');
        Route::get('academic-years/current/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getCurrent'])
            ->name('academic-years.current');

        // Bulk operations (must be before apiResource)
        Route::post('academic-years/bulk-create', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'bulkCreate'])
            ->name('academic-years.bulk-create');

        // Statistics and trends (must be before apiResource)
        Route::get('academic-years/statistics', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getStatistics'])
            ->name('academic-years.statistics');
        Route::get('academic-years/trends', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getTrends'])
            ->name('academic-years.trends');

        // Main CRUD resource
        Route::apiResource('academic-years', \App\Http\Controllers\API\V1\School\AcademicYearController::class);

        // Specific year operations (must be after apiResource)
        Route::post('academic-years/{academicYear}/set-as-current', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'setAsCurrent'])
            ->name('academic-years.set-as-current');
        Route::get('academic-years/{academicYear}/calendar', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getCalendar'])
            ->name('academic-years.calendar');
    });

    /*
    |--------------------------------------------------------------------------
    | Academic Term Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:api'])->group(function () {
        // Academic term queries (must be before apiResource to avoid conflicts)
        Route::get('academic-terms/by-academic-year/{academicYearId}', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getByAcademicYear'])
            ->name('academic-terms.by-academic-year');
        Route::get('academic-terms/current/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getCurrent'])
            ->name('academic-terms.current');

        // Bulk operations (must be before apiResource)
        Route::post('academic-terms/bulk-create', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'bulkCreate'])
            ->name('academic-terms.bulk-create');

        // Statistics and trends (must be before apiResource)
        Route::get('academic-terms/statistics', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getStatistics'])
            ->name('academic-terms.statistics');
        Route::get('academic-terms/trends', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getTrends'])
            ->name('academic-terms.trends');

        // Main CRUD resource
        Route::apiResource('academic-terms', \App\Http\Controllers\API\V1\School\AcademicTermController::class);

        // Specific term operations (must be after apiResource)
        Route::post('academic-terms/{academicTerm}/set-as-current', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'setAsCurrent'])
            ->name('academic-terms.set-as-current');
        Route::get('academic-terms/{academicTerm}/calendar', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getCalendar'])
            ->name('academic-terms.calendar');
    });

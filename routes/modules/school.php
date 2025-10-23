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
        // Health check
        Route::get('/health', [SchoolController::class, 'health']);

        // Basic CRUD operations
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        // Route::post('/test', action: [SchoolController::class, 'testStore']); // Test route for simplified creation
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

    Route::prefix('schools')->group(function () {
        // School-specific operations
        Route::get('{school}/dashboard', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'getDashboard'])
            ->name('schools.dashboard');
        Route::get('{school}/statistics', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'getStatistics'])
            ->name('schools.statistics');
        Route::get('{school}/students', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'getStudents'])
            ->name('schools.students');
        Route::get('{school}/academic-years', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'getAcademicYears'])
            ->name('schools.academic-years');
        Route::post('{school}/set-current-academic-year', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'setCurrentAcademicYear'])
            ->name('schools.set-current-academic-year');
        Route::get('{school}/performance-metrics', [\App\Http\Controllers\API\V1\School\SchoolController::class, 'getPerformanceMetrics'])
            ->name('schools.performance-metrics');
    });

    /*
    |--------------------------------------------------------------------------
    | Academic Year Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api'])->group(function () {
        Route::apiResource('academic-years', \App\Http\Controllers\API\V1\School\AcademicYearController::class);

        Route::prefix('academic-years')->group(function () {
        // Academic year queries
        Route::get('by-school/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getBySchool'])
            ->name('academic-years.by-school');
        Route::get('current/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getCurrent'])
            ->name('academic-years.current');

        // Academic year management
        Route::post('{academicYear}/set-as-current', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'setAsCurrent'])
            ->name('academic-years.set-as-current');
        Route::get('{academicYear}/calendar', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getCalendar'])
            ->name('academic-years.calendar');

        // Bulk operations
        Route::post('bulk-create', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'bulkCreate'])
            ->name('academic-years.bulk-create');

        // Statistics and trends
        Route::get('statistics', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getStatistics'])
            ->name('academic-years.statistics');
        Route::get('trends', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'getTrends'])
            ->name('academic-years.trends');

        // Search operations
        Route::get('search/by-year', [\App\Http\Controllers\API\V1\School\AcademicYearController::class, 'searchByYear'])
            ->name('academic-years.search-by-year');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Academic Term Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:api'])->group(function () {

    Route::apiResource('academic-terms', \App\Http\Controllers\API\V1\School\AcademicTermController::class);

    Route::prefix('academic-terms')->group(function () {
        // Academic term queries
        Route::get('by-academic-year/{academicYearId}', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getByAcademicYear'])
            ->name('academic-terms.by-academic-year');
        Route::get('current/{schoolId}', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getCurrent'])
            ->name('academic-terms.current');

        // Academic term management
        Route::post('{academicTerm}/set-as-current', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'setAsCurrent'])
            ->name('academic-terms.set-as-current');
        Route::get('{academicTerm}/calendar', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getCalendar'])
            ->name('academic-terms.calendar');

        // Bulk operations
        Route::post('bulk-create', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'bulkCreate'])
            ->name('academic-terms.bulk-create');

        // Statistics and trends
        Route::get('statistics', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getStatistics'])
            ->name('academic-terms.statistics');
        Route::get('trends', [\App\Http\Controllers\API\V1\School\AcademicTermController::class, 'getTrends'])
            ->name('academic-terms.trends');
    });
});

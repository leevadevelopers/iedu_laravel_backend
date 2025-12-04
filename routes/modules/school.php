 <?php

use App\Http\Controllers\API\V1\School\SchoolController;
use Illuminate\Support\Facades\Route;

// School Management with Form Engine Integration

/*
    |--------------------------------------------------------------------------
    | School Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api', 'tenant'])->prefix('schools')->group(function () {
        // Basic CRUD operations
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        // Route::post('/test', action: [SchoolController::class, 'testStore']); // Test route for simplified creation
        
        // Statistics route - must be before /{id} routes to avoid conflicts
        Route::get('/stats', [SchoolController::class, 'getAllSchoolsStatistics']);
        
        Route::get('/{id}', [SchoolController::class, 'show']);
        Route::put('/{id}', [SchoolController::class, 'update']);
        Route::delete('/{id}', [SchoolController::class, 'destroy']);

        // School-specific operations
        Route::get('/{id}/dashboard', [SchoolController::class, 'getDashboard']);
        Route::get('/{id}/statistics', [SchoolController::class, 'getStatistics']);
        Route::get('/{id}/students', [SchoolController::class, 'getStudents']);
        Route::get('/{id}/academic-years', [SchoolController::class, 'getAcademicYears']);
        Route::post('/{id}/set-current-academic-year', [SchoolController::class, 'setCurrentAcademicYear']);
        Route::get('/{id}/performance-metrics', [SchoolController::class, 'getPerformanceMetrics']);

        // Form Template Management
        Route::get('/form-templates', [SchoolController::class, 'getFormTemplates']);
        Route::get('/form-templates/{template}', [SchoolController::class, 'getFormTemplate']);
        Route::post('/form-templates', [SchoolController::class, 'createFormTemplate']);
        Route::put('/form-templates/{template}', [SchoolController::class, 'updateFormTemplate']);
        Route::delete('/form-templates/{template}', [SchoolController::class, 'deleteFormTemplate']);
        Route::post('/form-templates/{template}/duplicate', [SchoolController::class, 'duplicateFormTemplate']);

        // Form Engine Operations
        Route::post('/{id}/forms/submit', [SchoolController::class, 'processFormSubmission']);
        Route::get('/{id}/forms', [SchoolController::class, 'getFormInstances']);
        Route::get('/{id}/forms/{instanceId}', [SchoolController::class, 'getFormInstance']);
        Route::put('/{id}/forms/{instanceId}/status', [SchoolController::class, 'updateFormInstanceStatus']);
        Route::get('/{id}/forms/analytics', [SchoolController::class, 'getFormAnalytics']);
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


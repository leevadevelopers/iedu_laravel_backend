<?php

use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Student Management Routes
    |--------------------------------------------------------------------------
    */

    // Core Student CRUD Operations
    Route::apiResource('students', \App\Http\Controllers\API\V1\Student\StudentController::class);

    // Additional Student Operations
    Route::prefix('students')->group(function () {
        // Draft operations
        Route::post('draft', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'createDraft'])
            ->name('students.draft');
        Route::put('{student}/publish', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'publish'])
            ->name('students.publish');

        // Validation endpoints
        Route::post('validate-enrollment', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'validateEnrollment'])
            ->name('students.validate-enrollment');

        // CSV Import operations
        Route::post('import', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'import'])
            ->name('students.import');
        Route::get('import/template', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'downloadImportTemplate'])
            ->name('students.import.template');

        // Academic summary for a specific student
        Route::get('{student}/academic-summary', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'academicSummary'])
            ->name('students.academic-summary');

        // Transfer student to another school
        Route::post('{student}/transfer', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'transfer'])
            ->name('students.transfer');

        // Bulk operations
        Route::prefix('bulk')->group(function () {
            Route::post('promote', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'bulkPromote'])
                ->name('students.bulk.promote');
        });

        // Analytics and Reporting
        Route::prefix('analytics')->group(function () {
            Route::get('enrollment-stats', [\App\Http\Controllers\API\V1\Student\StudentController::class, 'enrollmentStats'])
                ->name('students.analytics.enrollment-stats');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Student Document Management Routes
    |--------------------------------------------------------------------------
    */

    Route::apiResource('student-documents', \App\Http\Controllers\API\V1\Student\StudentDocumentController::class);

    Route::prefix('student-documents')->group(function () {
        // File operations
        Route::get('{document}/download', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'download'])
            ->name('student-documents.download');

        // Document queries
        Route::get('by-student/{studentId}', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'getByStudent'])
            ->name('student-documents.by-student');
        Route::get('requiring-attention', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'getRequiringAttention'])
            ->name('student-documents.requiring-attention');

        // Bulk operations
        Route::post('bulk-update-status', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'bulkUpdateStatus'])
            ->name('student-documents.bulk-update-status');

        // Statistics
        Route::get('statistics', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'getStatistics'])
            ->name('student-documents.statistics');

        // Document types
        Route::get('document-types', [\App\Http\Controllers\API\V1\Student\StudentDocumentController::class, 'getDocumentTypes'])
            ->name('student-documents.document-types');

    });

    /*
    |--------------------------------------------------------------------------
    | Student Enrollment Management Routes
    |--------------------------------------------------------------------------
    */

    Route::apiResource('student-enrollments', \App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class);

    Route::prefix('student-enrollments')->group(function () {
        // Enrollment queries
        Route::get('by-student/{studentId}', [\App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class, 'getByStudent'])
            ->name('student-enrollments.by-student');
        Route::get('current/{studentId}', [\App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class, 'getCurrentEnrollment'])
            ->name('student-enrollments.current');

        // Bulk operations
        Route::prefix('bulk')->group(function () {
            Route::post('enroll', [\App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class, 'bulkEnroll'])
                ->name('student-enrollments.bulk.enroll');
            Route::post('transfer', [\App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class, 'bulkTransfer'])
                ->name('student-enrollments.bulk.transfer');
        });

        // Statistics and trends
        // Route::get('trends', [\App\Http\Controllers\API\V1\Student\StudentEnrollmentController::class, 'getEnrollmentTrends'])
        //     ->name('student-enrollments.trends');
    });

    /*
    |--------------------------------------------------------------------------
    | Family Relationship Management Routes
    |--------------------------------------------------------------------------
    */

    Route::apiResource('family-relationships', \App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class);

    Route::prefix('family-relationships')->group(function () {
        // Relationship queries
        Route::get('by-student/{studentId}', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'getByStudent'])
            ->name('family-relationships.by-student');
        Route::get('primary-contact/{studentId}', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'getPrimaryContact'])
            ->name('family-relationships.primary-contact');
        Route::get('emergency-contacts/{studentId}', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'getEmergencyContacts'])
            ->name('family-relationships.emergency-contacts');
        Route::get('authorized-pickup/{studentId}', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'getAuthorizedPickupPersons'])
            ->name('family-relationships.authorized-pickup');

        // Relationship management
        Route::post('set-primary-contact/{studentId}', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'setPrimaryContact'])
            ->name('family-relationships.set-primary-contact');

        // Bulk operations
        Route::post('bulk-create', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'bulkCreate'])
            ->name('family-relationships.bulk-create');

        // Search and statistics
        Route::get('search-potential-members', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'searchPotentialMembers'])
            ->name('family-relationships.search-potential-members');
        Route::get('statistics', [\App\Http\Controllers\API\V1\Student\FamilyRelationshipController::class, 'getStatistics'])
            ->name('family-relationships.statistics');
    });
});

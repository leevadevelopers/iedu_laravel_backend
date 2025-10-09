<?php

use App\Http\Controllers\API\V1\Assessment\AssessmentController;
use App\Http\Controllers\API\V1\Assessment\AssessmentTermController;
use App\Http\Controllers\API\V1\Assessment\GradeEntryController;
use App\Http\Controllers\API\V1\Assessment\GradeReviewController;
use App\Http\Controllers\API\V1\Assessment\GradebookController;
use App\Http\Controllers\API\V1\Assessment\AssessmentSettingsController;
use App\Http\Controllers\API\V1\Assessment\ReportController;
use App\Http\Controllers\API\V1\Assessment\GradeScaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessment & Grades API Routes
|--------------------------------------------------------------------------
|
| Routes for the Assessment and Grades module
|
*/

Route::prefix('v1/assessments')->group(function () {
    
    // Assessment Routes
    Route::apiResource('', AssessmentController::class)->parameters(['' => 'assessment']);
    Route::patch('{assessment}/status', [AssessmentController::class, 'updateStatus']);
    Route::post('{assessment}/lock', [AssessmentController::class, 'lock']);
    
    // Grade Entry Routes
    Route::prefix('grades')->group(function () {
        Route::get('/', [GradeEntryController::class, 'index']);
        Route::post('/', [GradeEntryController::class, 'store']);
        Route::get('{gradeEntry}', [GradeEntryController::class, 'show']);
        Route::put('{gradeEntry}', [GradeEntryController::class, 'update']);
        Route::delete('{gradeEntry}', [GradeEntryController::class, 'destroy']);
        
        // Student grades
        Route::get('student/{studentId}', [GradeEntryController::class, 'studentGrades']);
        
        // Bulk operations
        Route::post('bulk-import', [GradeEntryController::class, 'bulkImport']);
    });
    
    // Publish grades for an assessment
    Route::post('{assessment}/grades/publish', [GradeEntryController::class, 'publishGrades']);
    
    // Grade Review Routes
    Route::prefix('grade-reviews')->group(function () {
        Route::get('/', [GradeReviewController::class, 'index']);
        Route::post('/', [GradeReviewController::class, 'store']);
        Route::get('{gradeReview}', [GradeReviewController::class, 'show']);
        Route::put('{gradeReview}', [GradeReviewController::class, 'update']);
        Route::delete('{gradeReview}', [GradeReviewController::class, 'destroy']);
    });
    
    // Grade Scale Routes
    Route::prefix('grade-scales')->group(function () {
        Route::get('/', [GradeScaleController::class, 'index']);
        Route::post('/', [GradeScaleController::class, 'store']);
        Route::get('default', [GradeScaleController::class, 'getDefault']);
        Route::get('grading-system/{gradingSystemId}', [GradeScaleController::class, 'getByGradingSystem']);
        Route::get('{gradeScale}', [GradeScaleController::class, 'show']);
        Route::put('{gradeScale}', [GradeScaleController::class, 'update']);
        Route::delete('{gradeScale}', [GradeScaleController::class, 'destroy']);
        
        // Range management
        Route::post('{gradeScale}/ranges', [GradeScaleController::class, 'updateRange']);
        Route::delete('ranges/{range}', [GradeScaleController::class, 'deleteRange']);
        
        // Conversion utilities
        Route::post('{gradeScale}/convert', [GradeScaleController::class, 'convertScore']);
        Route::post('convert-between', [GradeScaleController::class, 'convertBetweenScales']);
        Route::post('{gradeScale}/calculate-gpa', [GradeScaleController::class, 'calculateGPA']);
    });
    
    // Gradebook Routes
    Route::prefix('gradebooks')->group(function () {
        Route::get('/', [GradebookController::class, 'index']);
        Route::post('/', [GradebookController::class, 'store']);
        Route::get('{gradebook}', [GradebookController::class, 'show']);
        Route::delete('{gradebook}', [GradebookController::class, 'destroy']);
        Route::get('{gradebook}/download', [GradebookController::class, 'download']);
        Route::post('{gradebook}/approve', [GradebookController::class, 'approve']);
        Route::post('{gradebook}/reject', [GradebookController::class, 'reject']);
        Route::post('{gradebook}/generate', [GradebookController::class, 'generate']);
    });
    
    // Assessment Settings Routes
    Route::prefix('settings')->group(function () {
        Route::get('/', [AssessmentSettingsController::class, 'index']);
        Route::post('/', [AssessmentSettingsController::class, 'store']);
        Route::get('term/{termId}', [AssessmentSettingsController::class, 'getByTerm']);
        Route::get('{assessmentSetting}', [AssessmentSettingsController::class, 'show']);
        Route::put('{assessmentSetting}', [AssessmentSettingsController::class, 'update']);
        Route::delete('{assessmentSetting}', [AssessmentSettingsController::class, 'destroy']);
    });
    
    // Report Routes
    Route::prefix('reports')->group(function () {
        Route::get('class/{classId}/term/{termId}/grades-summary', [ReportController::class, 'classGradesSummary']);
        Route::get('student/{studentId}/term/{termId}/transcript', [ReportController::class, 'studentTranscript']);
    });
});


<?php

use App\Http\Controllers\API\V1\Academic\SubjectController;
use App\Http\Controllers\API\V1\Academic\AcademicClassController;
use App\Http\Controllers\API\V1\Academic\AnalyticsController;
use App\Http\Controllers\API\V1\Academic\BulkOperationsController;
use App\Http\Controllers\API\V1\Academic\GradeEntryController;
use App\Http\Controllers\API\V1\Academic\GradeLevelController;
use App\Http\Controllers\API\V1\Academic\GradeScaleController;
use App\Http\Controllers\API\V1\Academic\GradingSystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Academic Management API Routes
|--------------------------------------------------------------------------
|
| These routes handle all academic management functionality including
| academic years, subjects, classes, grading systems, and grade entries.
| All routes are school-scoped and require authentication.
|
*/

Route::middleware(['auth:api', 'school.context'])->group(function () {

    // Subjects Management
    Route::prefix('subjects')->name('subjects.')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->name('index');
        Route::post('/', [SubjectController::class, 'store'])->name('store');
        Route::get('/core', [SubjectController::class, 'core'])->name('core');
        Route::get('/electives', [SubjectController::class, 'electives'])->name('electives');
        Route::get('/grade-level/{gradeLevel}', [SubjectController::class, 'byGradeLevel'])->name('by-grade-level');
        Route::get('/{subject}', [SubjectController::class, 'show'])->name('show');
        Route::put('/{subject}', [SubjectController::class, 'update'])->name('update');
        Route::delete('/{subject}', [SubjectController::class, 'destroy'])->name('destroy');
    });

    // Classes Management
    Route::prefix('classes')->name('classes.')->group(function () {
        Route::get('/', [AcademicClassController::class, 'index'])->name('index');
        Route::post('/', [AcademicClassController::class, 'store'])->name('store');
        Route::get('/teacher', [AcademicClassController::class, 'teacherClasses'])->name('teacher');
        Route::get('/{class}', [AcademicClassController::class, 'show'])->name('show');
        Route::put('/{class}', [AcademicClassController::class, 'update'])->name('update');
        Route::delete('/{class}', [AcademicClassController::class, 'destroy'])->name('destroy');

        // Class Enrollment Management
        Route::post('/{class}/students', [AcademicClassController::class, 'enrollStudent'])->name('enroll-student');
        Route::delete('/{class}/students', [AcademicClassController::class, 'removeStudent'])->name('remove-student');
        Route::get('/{class}/roster', [AcademicClassController::class, 'roster'])->name('roster');
    });

    // Grading Systems Management
    Route::prefix('grading-systems')->name('grading-systems.')->group(function () {
        Route::get('/', [GradingSystemController::class, 'index'])->name('index');
        Route::post('/', [GradingSystemController::class, 'store'])->name('store');
        Route::get('/primary', [GradingSystemController::class, 'primary'])->name('primary');
        Route::get('/{gradingSystem}', [GradingSystemController::class, 'show'])->name('show');
        Route::put('/{gradingSystem}', [GradingSystemController::class, 'update'])->name('update');
        Route::delete('/{gradingSystem}', [GradingSystemController::class, 'destroy'])->name('destroy');
        Route::post('/{gradingSystem}/set-primary', [GradingSystemController::class, 'setPrimary'])->name('set-primary');

        // Grade Scales Management
        Route::get('/{gradingSystem}/scales', [GradeScaleController::class, 'index'])->name('scales.index');
        Route::post('/{gradingSystem}/scales', [GradeScaleController::class, 'store'])->name('scales.store');
    });

    // Grade Scales Management
    Route::prefix('grade-scales')->name('grade-scales.')->group(function () {
        Route::get('/{gradeScale}', [GradeScaleController::class, 'show'])->name('show');
        Route::put('/{gradeScale}', [GradeScaleController::class, 'update'])->name('update');
        Route::delete('/{gradeScale}', [GradeScaleController::class, 'destroy'])->name('destroy');

        // Grade Levels Management
        Route::get('/{gradeScale}/levels', [GradeLevelController::class, 'index'])->name('levels.index');
        Route::post('/{gradeScale}/levels', [GradeLevelController::class, 'store'])->name('levels.store');
    });

    // Grade Entries Management
    Route::prefix('grade-entries')->name('grade-entries.')->group(function () {
        Route::get('/', [GradeEntryController::class, 'index'])->name('index');
        Route::post('/', [GradeEntryController::class, 'store'])->name('store');
        Route::post('/bulk', [GradeEntryController::class, 'bulkStore'])->name('bulk-store');
        Route::get('/student', [GradeEntryController::class, 'studentGrades'])->name('student-grades');
        Route::get('/class', [GradeEntryController::class, 'classGrades'])->name('class-grades');
        Route::get('/gpa/calculate', [GradeEntryController::class, 'calculateGPA'])->name('calculate-gpa');
        Route::get('/{gradeEntry}', [GradeEntryController::class, 'show'])->name('show');
        Route::put('/{gradeEntry}', [GradeEntryController::class, 'update'])->name('update');
        Route::delete('/{gradeEntry}', [GradeEntryController::class, 'destroy'])->name('destroy');
    });

    // Academic Analytics and Reports
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/academic-overview', [AnalyticsController::class, 'academicOverview'])->name('academic-overview');
        Route::get('/grade-distribution', [AnalyticsController::class, 'gradeDistribution'])->name('grade-distribution');
        Route::get('/subject-performance', [AnalyticsController::class, 'subjectPerformance'])->name('subject-performance');
        Route::get('/teacher-stats', [AnalyticsController::class, 'teacherStats'])->name('teacher-stats');
        Route::get('/class-stats/{class}', [AnalyticsController::class, 'classStats'])->name('class-stats');
    });

    // Bulk Operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/class-creation', [BulkOperationsController::class, 'createClasses'])->name('create-classes');
        Route::post('/student-enrollment', [BulkOperationsController::class, 'enrollStudents'])->name('enroll-students');
        Route::post('/grade-import', [BulkOperationsController::class, 'importGrades'])->name('import-grades');
        Route::post('/report-cards', [BulkOperationsController::class, 'generateReportCards'])->name('generate-report-cards');
    });

});

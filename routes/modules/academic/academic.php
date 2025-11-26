<?php

use App\Http\Controllers\API\V1\Academic\SubjectController;
use App\Http\Controllers\API\V1\Academic\AcademicClassController;
use App\Http\Controllers\API\V1\Academic\AnalyticsController;
use App\Http\Controllers\API\V1\Academic\BulkOperationsController;
use App\Http\Controllers\API\V1\Academic\GradeEntryController;
use App\Http\Controllers\API\V1\Academic\GradeLevelController;
use App\Http\Controllers\API\V1\Academic\GradeScaleController;
use App\Http\Controllers\API\V1\Academic\GradingSystemController;
use App\Http\Controllers\API\V1\Academic\TeacherController;
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

Route::middleware(['auth:api', 'tenant'])->group(function () {

    // Subjects Management
    Route::prefix('subjects')->name('subjects.')->group(function () {
        Route::get('/', [SubjectController::class, 'index'])->name('index');
        Route::post('/', [SubjectController::class, 'store'])->name('store');
        Route::get('/core', [SubjectController::class, 'core'])->name('core');
        Route::get('/electives', [SubjectController::class, 'electives'])->name('electives');
        Route::get('/grade-level/{gradeLevel}', [SubjectController::class, 'byGradeLevel'])->name('by-grade-level');
        Route::get('/{id}', [SubjectController::class, 'show'])->name('show');
        Route::put('/{id}', [SubjectController::class, 'update'])->name('update');
        Route::delete('/{id}', [SubjectController::class, 'destroy'])->name('destroy');
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
        // Alias for roster to list students in a class (frontend expects this path)
        Route::get('/{class}/students', [AcademicClassController::class, 'roster'])->name('students');
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
        Route::get('/', [GradeScaleController::class, 'index'])->name('index');
        Route::post('/', [GradeScaleController::class, 'store'])->name('store');
        Route::get('/default', [GradeScaleController::class, 'default'])->name('default');
        Route::get('/type/{type}', [GradeScaleController::class, 'byType'])->name('by-type');
        Route::get('/{gradeScale}', [GradeScaleController::class, 'show'])->name('show');
        Route::put('/{gradeScale}', [GradeScaleController::class, 'update'])->name('update');
        Route::delete('/{gradeScale}', [GradeScaleController::class, 'destroy'])->name('destroy');
        Route::post('/{gradeScale}/set-default', [GradeScaleController::class, 'setDefault'])->name('set-default');
        Route::get('/{gradeScale}/grade-for-percentage', [GradeScaleController::class, 'getGradeForPercentage'])->name('grade-for-percentage');

        // Grade Levels Management
        Route::get('/{gradeScale}/levels', [GradeLevelController::class, 'index'])->name('levels.index');
        Route::post('/{gradeScale}/levels', [GradeLevelController::class, 'store'])->name('levels.store');
    });

    // Grade Levels Management
    Route::prefix('grade-levels')->name('grade-levels.')->group(function () {
        Route::get('/', [GradeLevelController::class, 'index'])->name('index');
        Route::post('/', [GradeLevelController::class, 'store'])->name('store');
        Route::get('/passing', [GradeLevelController::class, 'passing'])->name('passing');
        Route::get('/failing', [GradeLevelController::class, 'failing'])->name('failing');
        Route::post('/reorder', [GradeLevelController::class, 'reorder'])->name('reorder');
        Route::get('/grade-for-percentage', [GradeLevelController::class, 'getGradeForPercentage'])->name('grade-for-percentage');
        Route::get('/{id}', [GradeLevelController::class, 'show'])->name('show');
        Route::put('/{id}', [GradeLevelController::class, 'update'])->name('update');
        Route::delete('/{id}', [GradeLevelController::class, 'destroy'])->name('destroy');
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
        Route::get('/student-performance-trends', [AnalyticsController::class, 'studentPerformanceTrends'])->name('student-performance-trends');
        Route::get('/attendance-analytics', [AnalyticsController::class, 'attendanceAnalytics'])->name('attendance-analytics');
        Route::get('/comparative-analytics', [AnalyticsController::class, 'comparativeAnalytics'])->name('comparative-analytics');
        Route::post('/export', [AnalyticsController::class, 'exportAnalytics'])->name('export');
    });

    // Bulk Operations
    Route::prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/class-creation', [BulkOperationsController::class, 'createClasses'])->name('create-classes');
        Route::post('/student-enrollment', [BulkOperationsController::class, 'enrollStudents'])->name('enroll-students');
        Route::post('/grade-import', [BulkOperationsController::class, 'importGrades'])->name('import-grades');
        Route::post('/report-cards', [BulkOperationsController::class, 'generateReportCards'])->name('generate-report-cards');
        Route::post('/update-students', [BulkOperationsController::class, 'updateStudents'])->name('update-students');
        Route::post('/create-teachers', [BulkOperationsController::class, 'createTeachers'])->name('create-teachers');
        Route::post('/create-subjects', [BulkOperationsController::class, 'createSubjects'])->name('create-subjects');
        Route::post('/transfer-students', [BulkOperationsController::class, 'transferStudents'])->name('transfer-students');
        Route::get('/operation-status/{operationId}', [BulkOperationsController::class, 'getOperationStatus'])->name('operation-status');
        Route::delete('/cancel-operation/{operationId}', [BulkOperationsController::class, 'cancelOperation'])->name('cancel-operation');
    });

    // Teachers Management
    Route::prefix('teachers')->name('teachers.')->group(function () {
        Route::get('/', [TeacherController::class, 'index'])->name('index');
        Route::post('/', [TeacherController::class, 'store'])->name('store');
        Route::get('/search', [TeacherController::class, 'search'])->name('search');
        Route::get('/by-department', [TeacherController::class, 'byDepartment'])->name('by-department');
        Route::get('/by-employment-type', [TeacherController::class, 'byEmploymentType'])->name('by-employment-type');
        Route::get('/by-specialization', [TeacherController::class, 'bySpecialization'])->name('by-specialization');
        Route::get('/by-grade-level', [TeacherController::class, 'byGradeLevel'])->name('by-grade-level');
        Route::get('/available-at', [TeacherController::class, 'availableAt'])->name('available-at');
        Route::get('/for-class-assignment', [TeacherController::class, 'forClassAssignment'])->name('for-class-assignment');
        Route::get('/{id}', [TeacherController::class, 'show'])->name('show');
        Route::put('/{id}', [TeacherController::class, 'update'])->name('update');
        Route::delete('/{id}', [TeacherController::class, 'destroy'])->name('destroy');

        // Teacher-specific actions
        Route::get('/{id}/workload', [TeacherController::class, 'workload'])->name('workload');
        Route::get('/{id}/classes', [TeacherController::class, 'classes'])->name('classes');
        Route::get('/{id}/statistics', [TeacherController::class, 'statistics'])->name('statistics');
        Route::get('/{id}/dashboard', [TeacherController::class, 'dashboard'])->name('dashboard');
        Route::put('/{id}/schedule', [TeacherController::class, 'updateSchedule'])->name('update-schedule');
        Route::post('/{id}/check-availability', [TeacherController::class, 'checkAvailability'])->name('check-availability');
        Route::post('/{id}/assign-to-class', [TeacherController::class, 'assignToClass'])->name('assign-to-class');
        Route::get('/{id}/performance-metrics', [TeacherController::class, 'performanceMetrics'])->name('performance-metrics');
    });

});

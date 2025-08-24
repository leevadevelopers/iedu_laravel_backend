<?php

use App\Http\Controllers\API\V1\SchoolManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('school')->group(function () {
    // Student Management
    Route::post('/students/enroll', [SchoolManagementController::class, 'enrollStudent']);
    Route::get('/students', [SchoolManagementController::class, 'getStudents']);
    Route::get('/students/{studentId}/summary', [SchoolManagementController::class, 'getStudentAcademicSummary']);

    // Class Management
    Route::post('/classes', [SchoolManagementController::class, 'createClass']);
    Route::get('/classes', [SchoolManagementController::class, 'getClasses']);
    Route::get('/classes/{classId}/students', [SchoolManagementController::class, 'getStudentsByClass']);
    Route::get('/classes/{classId}/statistics', [SchoolManagementController::class, 'getClassStatistics']);
    Route::post('/students/assign-class', [SchoolManagementController::class, 'assignStudentToClass']);

    // Form Templates
    Route::get('/form-templates', [SchoolManagementController::class, 'getSchoolFormTemplates']);
});

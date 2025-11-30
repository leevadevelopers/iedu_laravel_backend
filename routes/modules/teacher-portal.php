<?php

use App\Http\Controllers\API\V1\Teacher\TeacherPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Teacher Portal Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('teacher/portal')->name('teacher.portal.')->group(function () {
    Route::get('/dashboard', [TeacherPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/my-classes', [TeacherPortalController::class, 'myClasses'])->name('my-classes');
    Route::get('/class/{classId}/students', [TeacherPortalController::class, 'classStudents'])->name('class-students');
    Route::get('/my-schedule', [TeacherPortalController::class, 'mySchedule'])->name('my-schedule');
});


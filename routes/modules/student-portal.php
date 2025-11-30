<?php

use App\Http\Controllers\API\V1\Student\StudentPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Student Portal Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('student/portal')->name('student.portal.')->group(function () {
    Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/my-grades', [StudentPortalController::class, 'myGrades'])->name('my-grades');
    Route::get('/subject/{subjectId}/grades', [StudentPortalController::class, 'subjectGrades'])->name('subject-grades');
    Route::get('/my-attendance', [StudentPortalController::class, 'myAttendance'])->name('my-attendance');
    Route::get('/my-fees', [StudentPortalController::class, 'myFees'])->name('my-fees');
    Route::post('/pay-fees', [StudentPortalController::class, 'payFees'])->name('pay-fees');
});


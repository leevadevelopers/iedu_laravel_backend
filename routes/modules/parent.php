<?php

use App\Http\Controllers\API\V1\Parent\ParentPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Parent Portal Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('parent/portal')->name('parent.portal.')->group(function () {
    Route::get('/dashboard', [ParentPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/child/{childId}/grades', [ParentPortalController::class, 'getChildGrades'])->name('child.grades');
    Route::get('/child/{childId}/attendance', [ParentPortalController::class, 'getChildAttendance'])->name('child.attendance');
    Route::get('/child/{childId}/fees', [ParentPortalController::class, 'getChildFees'])->name('child.fees');
    Route::post('/justify-absence', [\App\Http\Controllers\API\V1\Parent\JustificationController::class, 'justifyAbsence'])->name('justify-absence');
    Route::get('/justifications/{studentId}', [\App\Http\Controllers\API\V1\Parent\JustificationController::class, 'getJustifications'])->name('justifications');
});


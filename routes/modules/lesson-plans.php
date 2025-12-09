<?php

use App\Http\Controllers\API\V1\Schedule\LessonPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->prefix('lesson-plans')->name('lesson-plans.')->group(function () {
    Route::get('/', [LessonPlanController::class, 'index'])->name('index');
    Route::get('/week', [LessonPlanController::class, 'week'])->name('week');
    Route::get('/calendar', [LessonPlanController::class, 'calendar'])->name('calendar');
    Route::get('/library', [LessonPlanController::class, 'library'])->name('library');
    Route::post('/', [LessonPlanController::class, 'store'])->name('store');
    Route::get('/{lessonPlan}', [LessonPlanController::class, 'show'])->name('show');
    Route::put('/{lessonPlan}', [LessonPlanController::class, 'update'])->name('update');
    Route::post('/{lessonPlan}/publish', [LessonPlanController::class, 'publish'])->name('publish');
    Route::post('/{lessonPlan}/duplicate', [LessonPlanController::class, 'duplicate'])->name('duplicate');
    Route::delete('/{lessonPlan}', [LessonPlanController::class, 'destroy'])->name('destroy');
    Route::post('/{lessonPlan}/share', [LessonPlanController::class, 'share'])->name('share');
    Route::post('/{lessonPlan}/attach-lesson', [LessonPlanController::class, 'attachLesson'])->name('attach-lesson');
});


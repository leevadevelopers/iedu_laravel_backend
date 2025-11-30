<?php

use App\Http\Controllers\API\V1\Schedule\ScheduleController;
use App\Http\Controllers\API\V1\Schedule\LessonController;
use App\Http\Controllers\API\V1\Schedule\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Schedule & Lessons Management Routes
|--------------------------------------------------------------------------
|
| These routes handle all schedule and lesson management functionality
| including timetable management, lesson planning, and attendance tracking.
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Schedule Management Routes
    |--------------------------------------------------------------------------
    */

    // Core Schedule CRUD
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::post('/', [ScheduleController::class, 'store'])->name('store');
        Route::get('/{schedule}', [ScheduleController::class, 'show'])->name('show');
        Route::put('/{schedule}', [ScheduleController::class, 'update'])->name('update');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->name('destroy');

        // Schedule Actions
        Route::post('/{schedule}/generate-lessons', [ScheduleController::class, 'generateLessons'])
            ->name('generate-lessons');

        // Schedule Views
        Route::get('/teacher/my-schedule', [ScheduleController::class, 'teacherSchedule'])
            ->name('teacher-schedule');
        Route::get('/class/{classId}/schedule', [ScheduleController::class, 'classSchedule'])
            ->name('class-schedule');

        // Conflict Detection
        Route::post('/check-conflicts', [ScheduleController::class, 'checkConflicts'])
            ->name('check-conflicts');

        // Statistics
        Route::get('/stats/overview', [ScheduleController::class, 'stats'])
            ->name('stats');
    });

    /*
    |--------------------------------------------------------------------------
    | Lesson Management Routes
    |--------------------------------------------------------------------------
    */

    // Core Lesson CRUD
    Route::prefix('lessons')->name('lessons.')->group(function () {
        Route::get('/', [LessonController::class, 'index'])->name('index');
        Route::post('/', [LessonController::class, 'store'])->name('store');
        Route::get('/{lesson}', [LessonController::class, 'show'])->name('show');
        Route::put('/{lesson}', [LessonController::class, 'update'])->name('update');
        Route::delete('/{lesson}', [LessonController::class, 'destroy'])->name('destroy');

        // Lesson State Management
        Route::post('/{lesson}/start', [LessonController::class, 'start'])->name('start');
        Route::post('/{lesson}/complete', [LessonController::class, 'complete'])->name('complete');
        Route::post('/{lesson}/cancel', [LessonController::class, 'cancel'])->name('cancel');

        // Attendance Management
        Route::get('/{lesson}/attendance', [LessonController::class, 'getAttendance'])
            ->name('get-attendance');
        Route::post('/{lesson}/attendance', [LessonController::class, 'markAttendance'])
            ->name('mark-attendance');
        Route::post('/{lesson}/attendance/quick-mark-all', [LessonController::class, 'quickMarkAll'])
            ->name('quick-mark-all');
        Route::get('/{lesson}/qr-code', [LessonController::class, 'generateQR'])
            ->name('generate-qr');
        Route::post('/{lesson}/check-in-qr', [LessonController::class, 'checkInQR'])
            ->name('check-in-qr');

        // Content Management
        Route::post('/{lesson}/contents', [LessonController::class, 'addContent'])
            ->name('add-content');

        // Reports and Analytics
        Route::get('/{lesson}/report', [LessonController::class, 'exportReport'])
            ->name('export-report');
        Route::get('/stats/overview', [LessonController::class, 'stats'])
            ->name('stats');
        Route::get('/stats/attendance', [LessonController::class, 'attendanceStats'])
            ->name('attendance-stats');
    });

    /*
    |--------------------------------------------------------------------------
    | Lesson Contents Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('lesson-contents')->name('lesson-contents.')->group(function () {
        Route::get('/{lessonContent}/download', [LessonController::class, 'downloadContent'])->name('download');
    });

    /*
    |--------------------------------------------------------------------------
    | Attendance Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::post('/bulk-mark', [AttendanceController::class, 'bulkMark'])->name('bulk-mark');
        Route::get('/class/{classId}/summary', [AttendanceController::class, 'classSummary'])->name('class-summary');
    });

    /*
    |--------------------------------------------------------------------------
    | Quick Access Routes for Different User Types
    |--------------------------------------------------------------------------
    */

    // Teacher Portal Routes
    Route::prefix('teacher/portal')->name('teacher.portal.')->group(function () {
        Route::get('/dashboard', [ScheduleController::class, 'teacherDashboard'])->name('dashboard');
        Route::get('/schedule', [ScheduleController::class, 'teacherSchedule'])->name('schedule');
        Route::get('/lessons', [LessonController::class, 'teacherLessons'])->name('lessons');
    });

    // Student Portal Routes
    Route::prefix('student/portal')->name('student.portal.')->group(function () {
        Route::get('/schedule', [ScheduleController::class, 'studentSchedule'])->name('schedule');
        Route::get('/lessons/today', [LessonController::class, 'studentTodayLessons'])->name('lessons.today');
        Route::get('/lessons/upcoming', [LessonController::class, 'studentUpcomingLessons'])->name('lessons.upcoming');
    });

    // Parent Portal Routes
    Route::prefix('parent/portal')->name('parent.portal.')->group(function () {
        Route::get('/children-schedule', [ScheduleController::class, 'parentChildrenSchedule'])->name('children-schedule');
    });

});

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Public schedule view (for display boards, etc.)
Route::get('/public/schedule/{token}', function ($token) {
    // Implement token-based public schedule access
    // This could be used for digital displays in school hallways
    return response()->json(['message' => 'Public schedule access not implemented']);
})->name('public.schedule');

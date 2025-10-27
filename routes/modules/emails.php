<?php

use App\Http\Controllers\API\V1\EmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Email Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {
    // Student welcome email
    Route::post('/emails/send-student-welcome', [EmailController::class, 'sendStudentWelcomeEmail'])
        ->name('emails.send-student-welcome');

    // Resend password reset email
    Route::post('/emails/resend-password-reset', [EmailController::class, 'resendPasswordReset'])
        ->name('emails.resend-password-reset');
});

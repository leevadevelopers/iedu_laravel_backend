<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Notification\{
    SmsController,
    EmailController
};

// Notification routes with v1 prefix to be consistent with other modules
// Route::prefix('v1/notification')->group(function () {
//     Route::middleware(['auth:api', 'tenant'])->group(function () {
//         Route::post('email', [EmailController::class, 'sendEmail']);
//         Route::post('sms', [SmsController::class, 'sendSms']);
//     });
// });

// // Legacy notification routes without v1 prefix for backward compatibility
// Route::prefix('notification')->group(function () {
//     Route::middleware(['auth:api', 'tenant'])->group(function () {
//         Route::post('email', [EmailController::class, 'sendEmail']);
//         Route::post('sms', [SmsController::class, 'sendSms']);
//     });
// });

// Test email route (for debugging)
// Route::get('test/email', function () {
//     return response()->json([
//         'message' => 'Email notification system is accessible',
//         'endpoints' => [
//             'send_email' => '/api/v1/notification/email',
//             'send_sms' => '/api/v1/notification/sms'
//         ],
//         'email_controller_exists' => class_exists(\App\Http\Controllers\Notification\EmailController::class),
//         'sms_controller_exists' => class_exists(\App\Http\Controllers\Notification\SmsController::class),
//         'timestamp' => now()->toISOString()
//     ]);
// });

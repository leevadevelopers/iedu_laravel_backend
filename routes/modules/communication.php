<?php

use App\Http\Controllers\API\V1\Communication\CommunicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Communication Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('communications')->name('communications.')->group(function () {

    // SMS Routes
    Route::prefix('sms')->name('sms.')->group(function () {
        Route::post('/send', [CommunicationController::class, 'sendSMS'])->name('send');
        Route::post('/bulk', [CommunicationController::class, 'sendBulkSMS'])->name('bulk');
        Route::get('/history', [CommunicationController::class, 'getSMSHistory'])->name('history');
        Route::get('/balance', [CommunicationController::class, 'getSMSBalance'])->name('balance');
    });

    // Announcements Routes
    Route::prefix('announcements')->name('announcements.')->group(function () {
        Route::get('/', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'store'])->name('store');
        Route::get('/{announcement}', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'show'])->name('show');
        Route::put('/{announcement}', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'update'])->name('update');
        Route::delete('/{announcement}', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'destroy'])->name('destroy');
        Route::post('/{announcement}/publish', [\App\Http\Controllers\API\V1\Communication\AnnouncementController::class, 'publish'])->name('publish');
    });

    // Messaging Routes
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::post('/', [\App\Http\Controllers\API\V1\Communication\MessagingController::class, 'send'])->name('send');
        Route::get('/inbox', [\App\Http\Controllers\API\V1\Communication\MessagingController::class, 'inbox'])->name('inbox');
        Route::get('/sent', [\App\Http\Controllers\API\V1\Communication\MessagingController::class, 'sent'])->name('sent');
        Route::get('/{message}/thread', [\App\Http\Controllers\API\V1\Communication\MessagingController::class, 'thread'])->name('thread');
        Route::put('/{message}/read', [\App\Http\Controllers\API\V1\Communication\MessagingController::class, 'markAsRead'])->name('read');
    });
});


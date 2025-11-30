<?php

use App\Http\Controllers\API\V1\AI\AITutorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Tutor Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai-tutor')->name('ai-tutor.')->group(function () {
    Route::post('/ask', [AITutorController::class, 'ask'])->name('ask');
    Route::get('/history', [AITutorController::class, 'history'])->name('history');
    Route::get('/usage', [AITutorController::class, 'usage'])->name('usage');
});


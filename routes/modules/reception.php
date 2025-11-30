<?php

use App\Http\Controllers\API\V1\Reception\ReceptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reception Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('reception')->name('reception.')->group(function () {
    Route::post('/visitors', [ReceptionController::class, 'logVisitor'])->name('visitors.store');
    Route::get('/visitors', [ReceptionController::class, 'listVisitors'])->name('visitors.index');
    Route::post('/visitors/{visitor}/resolve', [ReceptionController::class, 'markResolved'])->name('visitors.resolve');
});


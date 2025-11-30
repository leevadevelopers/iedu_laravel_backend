<?php

use App\Http\Controllers\API\V1\Documents\DocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Documents Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/templates', [DocumentController::class, 'templates'])->name('templates');
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::post('/generate', [DocumentController::class, 'generate'])->name('generate');
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
});


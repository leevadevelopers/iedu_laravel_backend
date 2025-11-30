<?php

use App\Http\Controllers\API\V1\Director\DirectorPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Director Portal Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('director/portal')->name('director.portal.')->group(function () {
    Route::get('/dashboard', [DirectorPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/statistics', [DirectorPortalController::class, 'statistics'])->name('statistics');
});


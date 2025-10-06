<?php

use App\Http\Controllers\API\V1\Library\BookController;
use App\Http\Controllers\API\V1\Library\LoanController;
use App\Http\Controllers\API\V1\Library\ReservationController;
use App\Http\Controllers\API\V1\Library\IncidentController;
use App\Http\Controllers\API\V1\Library\CollectionController;
use App\Http\Controllers\API\V1\Library\AuthorController;
use App\Http\Controllers\API\V1\Library\PublisherController;
use App\Http\Controllers\API\V1\Library\BookFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Library Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('library')->name('library.')->group(function () {

    // ========================================
    // Library Statistics & Reports
    // ========================================
    Route::prefix('statistics')->name('statistics.')->group(function () {
        Route::get('/dashboard', [BookController::class, 'dashboard'])->name('dashboard');
        Route::get('/popular-books', [BookController::class, 'popularBooks'])->name('popular-books');
        Route::get('/loan-stats', [LoanController::class, 'statistics'])->name('loan-stats');
    });

    // ========================================
    // Collections
    // ========================================
    Route::prefix('collections')->name('collections.')->group(function () {
        Route::get('/', [CollectionController::class, 'index'])->name('index');
        Route::post('/', [CollectionController::class, 'store'])->name('store');
        Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
        Route::put('/{collection}', [CollectionController::class, 'update'])->name('update');
        Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('destroy');
    });

    // ========================================
    // Authors
    // ========================================
    Route::prefix('authors')->name('authors.')->group(function () {
        Route::get('/', [AuthorController::class, 'index'])->name('index');
        Route::post('/', [AuthorController::class, 'store'])->name('store');
        Route::get('/{author}', [AuthorController::class, 'show'])->name('show');
        Route::put('/{author}', [AuthorController::class, 'update'])->name('update');
        Route::delete('/{author}', [AuthorController::class, 'destroy'])->name('destroy');
    });

    // ========================================
    // Publishers
    // ========================================
    Route::prefix('publishers')->name('publishers.')->group(function () {
        Route::get('/', [PublisherController::class, 'index'])->name('index');
        Route::post('/', [PublisherController::class, 'store'])->name('store');
        Route::get('/{publisher}', [PublisherController::class, 'show'])->name('show');
        Route::put('/{publisher}', [PublisherController::class, 'update'])->name('update');
        Route::delete('/{publisher}', [PublisherController::class, 'destroy'])->name('destroy');
    });

    // ========================================
    // Books
    // ========================================
    Route::prefix('books')->name('books.')->group(function () {
        Route::get('/', [BookController::class, 'index'])->name('index');
        Route::post('/', [BookController::class, 'store'])->name('store');
        Route::get('/search', [BookController::class, 'search'])->name('search');
        Route::get('/{book}', [BookController::class, 'show'])->name('show');
        Route::put('/{book}', [BookController::class, 'update'])->name('update');
        Route::delete('/{book}', [BookController::class, 'destroy'])->name('destroy');

        // Book Copies
        Route::get('/{book}/copies', [BookController::class, 'copies'])->name('copies');
        Route::post('/{book}/copies', [BookController::class, 'addCopy'])->name('add-copy');

        // Book Files
        // Route::post('/{book}/files', [BookFileController::class, 'store'])->name('files.store');
        // Route::delete('/{book}/files/{file}', [BookFileController::class, 'destroy'])->name('files.destroy');
        // Route::get('/{book}/files/{file}/download', [BookFileController::class, 'download'])->name('files.download');
    });

    // ========================================
    // Book Files (Standalone)
    // ========================================
    Route::prefix('book-files')->name('book-files.')->group(function () {
        Route::get('/', [BookFileController::class, 'index'])->name('index');
        Route::post('/', [BookFileController::class, 'store'])->name('store');
        Route::get('/{bookFile}', [BookFileController::class, 'show'])->name('show');
        Route::put('/{bookFile}', [BookFileController::class, 'update'])->name('update');
        Route::delete('/{bookFile}', [BookFileController::class, 'destroy'])->name('destroy');
        Route::get('/{bookFile}/download', [BookFileController::class, 'download'])->name('download');
    });

     // ========================================
    // Reservations
    // ========================================
    Route::prefix('reservations')->name('reservations.')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])->name('index');
        Route::post('/', [ReservationController::class, 'store'])->name('store');
        Route::get('/my-reservations', [ReservationController::class, 'myReservations'])->name('my-reservations');
        Route::get('/{reservation}', [ReservationController::class, 'show'])->name('show');
        Route::patch('/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('cancel');
        Route::patch('/{reservation}/ready', [ReservationController::class, 'markReady'])->name('mark-ready');
        Route::patch('/{reservation}/collected', [ReservationController::class, 'markCollected'])->name('mark-collected');
    });


    // ========================================
    // Loans
    // ========================================
    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [LoanController::class, 'index'])->name('index');
        Route::post('/', [LoanController::class, 'store'])->name('store');
        Route::get('/my-loans', [LoanController::class, 'myLoans'])->name('my-loans');
        Route::get('/overdue', [LoanController::class, 'overdue'])->name('overdue');
        Route::get('/{loan}', [LoanController::class, 'show'])->name('show');
        Route::patch('/{loan}/return', [LoanController::class, 'return'])->name('return');
        Route::patch('/{loan}/renew', [LoanController::class, 'renew'])->name('renew');
        Route::delete('/{loan}', [LoanController::class, 'destroy'])->name('destroy');
    });


    // ========================================
    // Incidents
    // ========================================
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::post('/', [IncidentController::class, 'store'])->name('store');
        Route::get('/{libraryIncident}', [IncidentController::class, 'show'])->name('show');
        Route::patch('/{libraryIncident}/resolve', [IncidentController::class, 'resolve'])->name('resolve');
        Route::patch('/{libraryIncident}/close', [IncidentController::class, 'close'])->name('close');
    });


});

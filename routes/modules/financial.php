<?php

use App\Http\Controllers\API\V1\Financial\InvoiceController;
use App\Http\Controllers\API\V1\Financial\PaymentController;
use App\Http\Controllers\API\V1\Financial\FeeController;
use App\Http\Controllers\API\V1\Financial\ExpenseController;
use App\Http\Controllers\API\V1\Financial\FinancialAccountController;
use App\Http\Controllers\API\V1\Financial\FinancialReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financial Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('finance')->name('finance.')->group(function () {

    // ========================================
    // Financial Accounts
    // ========================================
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/', [FinancialAccountController::class, 'index'])->name('index');
        Route::get('/list', [FinancialAccountController::class, 'index'])->name('list');
        Route::post('/', [FinancialAccountController::class, 'store'])->name('store');
        // Route::get('/{account}/transactions', [FinancialAccountController::class, 'transactions'])->name('transactions'); // Method not implemented yet
        Route::get('/{account}', [FinancialAccountController::class, 'show'])->name('show');
        Route::put('/{account}', [FinancialAccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [FinancialAccountController::class, 'destroy'])->name('destroy');
    });

    // ========================================
    // Invoices
    // ========================================
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::post('/', [InvoiceController::class, 'store'])->name('store');
        Route::get('/my-invoices', [InvoiceController::class, 'myInvoices'])->name('my-invoices');
        Route::get('/overdue', [InvoiceController::class, 'overdue'])->name('overdue');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');

        // Invoice Actions
        Route::post('/{invoice}/issue', [InvoiceController::class, 'issue'])->name('issue');
        Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/{invoice}/send', [InvoiceController::class, 'send'])->name('send');
        Route::get('/{invoice}/download', [InvoiceController::class, 'download'])->name('download');

        // Invoice Items
        Route::post('/{invoice}/items', [InvoiceController::class, 'addItem'])->name('add-item');
        Route::delete('/{invoice}/items/{item}', [InvoiceController::class, 'removeItem'])->name('remove-item');
    });

    // ========================================
    // Payments
    // ========================================
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
        Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])->name('receipt');
    });

    // ========================================
    // Fees
    // ========================================
    Route::prefix('fees')->name('fees.')->group(function () {
        Route::get('/', [FeeController::class, 'index'])->name('index');
        Route::post('/', [FeeController::class, 'store'])->name('store');
        Route::get('/{fee}', [FeeController::class, 'show'])->name('show');
        Route::put('/{fee}', [FeeController::class, 'update'])->name('update');
        Route::delete('/{fee}', [FeeController::class, 'destroy'])->name('destroy');

        // Fee Actions
        Route::post('/apply', [FeeController::class, 'apply'])->name('apply');
        Route::post('/bulk-apply', [FeeController::class, 'bulkApply'])->name('bulk-apply');
    });

    // ========================================
    // Expenses
    // ========================================
    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        Route::get('/{expense}/receipt', [ExpenseController::class, 'getReceipt'])->name('receipt');
    });

    // ========================================
    // Financial Reports
    // ========================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/summary', [FinancialReportController::class, 'summary'])->name('summary');
        Route::get('/income-statement', [FinancialReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/accounts-receivable', [FinancialReportController::class, 'accountsReceivable'])->name('accounts-receivable');
        Route::get('/accounts-payable', [FinancialReportController::class, 'accountsPayable'])->name('accounts-payable');
        Route::get('/revenue-by-category', [FinancialReportController::class, 'revenueByCategory'])->name('revenue-by-category');
        Route::get('/expenses-by-category', [FinancialReportController::class, 'expensesByCategory'])->name('expenses-by-category');
    });

    // ========================================
    // Financial Dashboard
    // ========================================
    Route::get('/dashboard', [FinancialReportController::class, 'dashboard'])->name('dashboard');
});

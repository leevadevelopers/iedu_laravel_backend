#!/bin/bash

echo "🛣️  Creating Routes..."

# ============================================
# LIBRARY ROUTES
# ============================================

cat > routes/modules/library.php << 'EOF'
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
        Route::get('/{book}/files', [BookFileController::class, 'index'])->name('files.index');
        Route::post('/{book}/files', [BookFileController::class, 'store'])->name('files.store');
        Route::get('/{book}/files/{file}', [BookFileController::class, 'show'])->name('files.show');
        Route::delete('/{book}/files/{file}', [BookFileController::class, 'destroy'])->name('files.destroy');
        Route::get('/{book}/files/{file}/download', [BookFileController::class, 'download'])->name('files.download');
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
    // Incidents
    // ========================================
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index');
        Route::post('/', [IncidentController::class, 'store'])->name('store');
        Route::get('/{incident}', [IncidentController::class, 'show'])->name('show');
        Route::patch('/{incident}/resolve', [IncidentController::class, 'resolve'])->name('resolve');
        Route::patch('/{incident}/close', [IncidentController::class, 'close'])->name('close');
    });

    // ========================================
    // Library Statistics & Reports
    // ========================================
    Route::prefix('statistics')->name('statistics.')->group(function () {
        Route::get('/dashboard', [BookController::class, 'dashboard'])->name('dashboard');
        Route::get('/popular-books', [BookController::class, 'popularBooks'])->name('popular-books');
        Route::get('/loan-stats', [LoanController::class, 'statistics'])->name('loan-stats');
    });
});
EOF

# ============================================
# FINANCIAL ROUTES
# ============================================

cat > routes/modules/financial.php << 'EOF'
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
        Route::post('/', [FinancialAccountController::class, 'store'])->name('store');
        Route::get('/{account}', [FinancialAccountController::class, 'show'])->name('show');
        Route::put('/{account}', [FinancialAccountController::class, 'update'])->name('update');
        Route::delete('/{account}', [FinancialAccountController::class, 'destroy'])->name('destroy');
        Route::get('/{account}/transactions', [FinancialAccountController::class, 'transactions'])->name('transactions');
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
EOF


echo "✅ All routes created successfully!"
echo ""
echo "📋 Available API Endpoints:"
echo ""
echo "=== Authentication ==="
echo "POST   /api/v1/auth/login"
echo "POST   /api/v1/auth/register"
echo "POST   /api/v1/auth/logout"
echo "GET    /api/v1/auth/me"
echo ""
echo "=== Library - Books ==="
echo "GET    /api/v1/library/books"
echo "POST   /api/v1/library/books"
echo "GET    /api/v1/library/books/{id}"
echo "PUT    /api/v1/library/books/{id}"
echo "DELETE /api/v1/library/books/{id}"
echo ""
echo "=== Library - Loans ==="
echo "GET    /api/v1/library/loans"
echo "POST   /api/v1/library/loans"
echo "GET    /api/v1/library/loans/my-loans"
echo "PATCH  /api/v1/library/loans/{id}/return"
echo ""
echo "=== Library - Reservations ==="
echo "GET    /api/v1/library/reservations"
echo "POST   /api/v1/library/reservations"
echo "PATCH  /api/v1/library/reservations/{id}/cancel"
echo ""
echo "=== Library - Incidents ==="
echo "GET    /api/v1/library/incidents"
echo "POST   /api/v1/library/incidents"
echo "PATCH  /api/v1/library/incidents/{id}/resolve"
echo ""
echo "=== Finance - Invoices ==="
echo "GET    /api/v1/finance/invoices"
echo "POST   /api/v1/finance/invoices"
echo "GET    /api/v1/finance/invoices/{id}"
echo "POST   /api/v1/finance/invoices/{id}/issue"
echo ""
echo "=== Finance - Payments ==="
echo "GET    /api/v1/finance/payments"
echo "POST   /api/v1/finance/payments"
echo ""
echo "=== Finance - Fees ==="
echo "GET    /api/v1/finance/fees"
echo "POST   /api/v1/finance/fees"
echo "POST   /api/v1/finance/fees/apply"
echo ""
echo "=== Finance - Reports ==="
echo "GET    /api/v1/finance/reports/summary"
echo "GET    /api/v1/finance/reports/income-statement"
echo "GET    /api/v1/finance/dashboard"
echo ""

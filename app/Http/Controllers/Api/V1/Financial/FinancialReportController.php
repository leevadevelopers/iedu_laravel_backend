<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Financial\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function summary(Request $request): JsonResponse
    {
        // $this->authorize('viewReports', Invoice::class);

        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $totalRevenue = Invoice::whereBetween('issued_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total');

        $totalPending = Invoice::whereBetween('issued_at', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'partially_paid'])
            ->sum('total');

        $totalOverdue = Invoice::where('status', 'overdue')
            ->sum('total');

        $totalExpenses = Expense::whereBetween('incurred_at', [$startDate, $endDate])
            ->sum('amount');

        $totalPayments = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'revenue' => [
                'total' => (float) $totalRevenue,
                'pending' => (float) $totalPending,
                'overdue' => (float) $totalOverdue,
            ],
            'expenses' => [
                'total' => (float) $totalExpenses,
            ],
            'payments' => [
                'total' => (float) $totalPayments,
            ],
            'net_income' => (float) ($totalRevenue - $totalExpenses),
        ], 'Financial summary retrieved successfully');
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $revenue = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        $expenses = Expense::whereBetween('incurred_at', [$startDate, $endDate])
            ->sum('amount');

        $grossProfit = (float) $revenue; // no COGS tracked, treat revenue as gross
        $operatingExpenses = (float) $expenses;
        $operatingIncome = $grossProfit - $operatingExpenses;

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'revenue' => (float) $revenue,
            'expenses' => (float) $expenses,
            'gross_profit' => (float) $grossProfit,
            'operating_expenses' => (float) $operatingExpenses,
            'operating_income' => (float) $operatingIncome,
            'net_income' => (float) $operatingIncome,
        ], 'Income statement generated successfully');
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $asOf = $request->get('as_of', now());

        $totalPayments = Payment::where('status', 'completed')
            ->where('paid_at', '<=', $asOf)
            ->sum('amount');

        $totalExpenses = Expense::where('incurred_at', '<=', $asOf)
            ->sum('amount');

        $openInvoices = Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where(function ($q) use ($asOf) {
                $q->whereNull('issued_at')->orWhere('issued_at', '<=', $asOf);
            })
            ->with(['payments' => function ($q) use ($asOf) {
                $q->where('status', 'completed')->where('paid_at', '<=', $asOf);
            }])
            ->get();

        $accountsReceivable = 0.0;
        foreach ($openInvoices as $inv) {
            $paid = (float) $inv->payments->sum('amount');
            $accountsReceivable += max(0, (float) $inv->total - $paid);
        }

        $assets = [
            'cash' => (float) max(0, $totalPayments - $totalExpenses),
            'accounts_receivable' => (float) $accountsReceivable,
        ];

        $liabilities = [
            // No liabilities model; treat none tracked
            'total' => 0.0,
        ];

        $equity = array_sum($assets) - $liabilities['total'];

        return $this->successResponse([
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => (float) $equity,
        ], 'Balance sheet generated successfully');
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $inflows = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        $outflows = Expense::whereBetween('incurred_at', [$startDate, $endDate])
            ->sum('amount');

        $byMethod = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->get();

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'inflows' => (float) $inflows,
            'outflows' => (float) $outflows,
            'net' => (float) ($inflows - $outflows),
            'by_method' => $byMethod,
        ], 'Cash flow generated successfully');
    }

    public function accountsReceivable(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $invoices = Invoice::whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->with(['payments' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get();

        $list = [];
        $totalOutstanding = 0.0;
        foreach ($invoices as $inv) {
            $paid = (float) $inv->payments->sum('amount');
            $outstanding = max(0, (float) $inv->total - $paid);
            $totalOutstanding += $outstanding;
            $list[] = [
                'invoice_id' => $inv->id,
                'reference' => $inv->reference,
                'total' => (float) $inv->total,
                'paid' => (float) $paid,
                'outstanding' => (float) $outstanding,
                'status' => $inv->status,
                'due_at' => $inv->due_at,
            ];
        }

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_outstanding' => (float) $totalOutstanding,
            'invoices' => $list,
        ], 'Accounts receivable report generated successfully');
    }

    public function accountsPayable(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $expenses = Expense::whereBetween('incurred_at', [$startDate, $endDate])
            ->orderBy('incurred_at', 'desc')
            ->get(['id', 'account_id', 'category', 'amount', 'description', 'incurred_at']);

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'total_payables' => (float) $expenses->sum('amount'),
            'expenses' => $expenses,
        ], 'Accounts payable report generated successfully');
    }

    public function revenueByCategory(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $data = \App\Models\V1\Financial\InvoiceItem::query()
            ->whereHas('invoice', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('issued_at', [$startDate, $endDate])
                  ->whereIn('status', ['issued', 'paid', 'partially_paid', 'overdue']);
            })
            ->selectRaw('COALESCE(fees.name, "Uncategorized") as category, SUM(invoice_items.total) as total')
            ->leftJoin('fees', 'fees.id', '=', 'invoice_items.fee_id')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'categories' => $data,
        ], 'Revenue by category generated successfully');
    }

    public function expensesByCategory(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now()->endOfMonth());

        $data = Expense::whereBetween('incurred_at', [$startDate, $endDate])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse([
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'categories' => $data,
        ], 'Expenses by category generated successfully');
    }
}

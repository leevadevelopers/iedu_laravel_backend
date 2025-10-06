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
        $this->authorize('viewReports', Invoice::class);

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
}

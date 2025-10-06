<?php

namespace App\Jobs\Financial;

use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Models\V1\Financial\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $tenantId, public int $month, public int $year)
    {
    }

    public function handle(): void
    {
        $startDate = now()->setYear($this->year)->setMonth($this->month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $totalRevenue = Invoice::where('tenant_id', $this->tenantId)
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total');

        $totalPending = Invoice::where('tenant_id', $this->tenantId)
            ->whereBetween('issued_at', [$startDate, $endDate])
            ->whereIn('status', ['issued', 'partially_paid'])
            ->sum('total');

        $totalExpenses = Expense::where('tenant_id', $this->tenantId)
            ->whereBetween('incurred_at', [$startDate, $endDate])
            ->sum('amount');

        $totalPayments = Payment::where('tenant_id', $this->tenantId)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->sum('amount');

        $report = [
            'period' => [
                'month' => $this->month,
                'year' => $this->year,
                'start' => $startDate->toISOString(),
                'end' => $endDate->toISOString(),
            ],
            'revenue' => [
                'total' => (float) $totalRevenue,
                'pending' => (float) $totalPending,
            ],
            'expenses' => [
                'total' => (float) $totalExpenses,
            ],
            'payments' => [
                'total' => (float) $totalPayments,
            ],
            'net_income' => (float) ($totalRevenue - $totalExpenses),
            'generated_at' => now()->toISOString(),
        ];

        $filename = "reports/tenant_{$this->tenantId}/financial_{$this->year}_{$this->month}.json";
        Storage::put($filename, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('Generated monthly financial report', [
            'tenant_id' => $this->tenantId,
            'month' => $this->month,
            'year' => $this->year,
            'filename' => $filename,
        ]);
    }
}

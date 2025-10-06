<?php

namespace App\Jobs\Library;

use App\Models\V1\Library\Loan;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Fee;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOverdueFinesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $overdueLoans = Loan::where('status', 'overdue')
            ->whereNull('fine_amount')
            ->get();

        foreach ($overdueLoans as $loan) {
            $daysOverdue = $loan->getDaysOverdue();
            $fineAmount = $daysOverdue * 10; // 10 MZN per day

            $loan->update(['fine_amount' => $fineAmount]);

            // Create invoice
            $invoice = Invoice::create([
                'tenant_id' => $loan->tenant_id,
                'billable_id' => $loan->borrower_id,
                'billable_type' => User::class,
                'subtotal' => $fineAmount,
                'total' => $fineAmount,
                'status' => 'issued',
                'issued_at' => now(),
                'due_at' => now()->addDays(7),
                'notes' => "Overdue fine for book: {$loan->bookCopy->book->title}",
            ]);

            $invoice->items()->create([
                'description' => "Overdue fine - {$daysOverdue} days",
                'quantity' => $daysOverdue,
                'unit_price' => 10,
                'total' => $fineAmount,
            ]);
        }

        Log::info('Processed overdue fines', ['count' => $overdueLoans->count()]);
    }
}

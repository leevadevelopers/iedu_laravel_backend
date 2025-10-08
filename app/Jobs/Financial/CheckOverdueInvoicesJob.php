<?php

namespace App\Jobs\Financial;

use App\Models\V1\Financial\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $overdueInvoices = Invoice::whereIn('status', ['issued', 'partially_paid'])
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $invoice->update(['status' => 'overdue']);
        }

        Log::info('Checked overdue invoices', ['count' => $overdueInvoices->count()]);
    }
}

<?php

namespace App\Jobs\Library;

use App\Events\Library\BookOverdue;
use App\Models\V1\Library\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckOverdueLoansJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $overdueLoans = Loan::where('status', 'active')
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdueLoans as $loan) {
            $loan->update(['status' => 'overdue']);

            event(new BookOverdue($loan));
        }

        Log::info('Checked overdue loans', ['count' => $overdueLoans->count()]);
    }
}

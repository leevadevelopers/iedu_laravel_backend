<?php

namespace App\Jobs\Library;

use App\Models\V1\Library\Incident;
use App\Models\V1\Financial\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncidentFineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Incident $incident)
    {
    }

    public function handle(): void
    {
        if (!$this->incident->assessed_fine || !$this->incident->loan) {
            return;
        }

        $invoice = Invoice::create([
            'tenant_id' => $this->incident->tenant_id,
            'billable_id' => $this->incident->loan->borrower_id,
            'billable_type' => User::class,
            'subtotal' => $this->incident->assessed_fine,
            'total' => $this->incident->assessed_fine,
            'status' => 'issued',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
            'notes' => "Fine for library incident: {$this->incident->type}",
        ]);

        $invoice->items()->create([
            'description' => "Incident fine - {$this->incident->type} - {$this->incident->bookCopy->book->title}",
            'quantity' => 1,
            'unit_price' => $this->incident->assessed_fine,
            'total' => $this->incident->assessed_fine,
        ]);

        Log::info('Processed incident fine', [
            'incident_id' => $this->incident->id,
            'amount' => $this->incident->assessed_fine,
        ]);
    }
}

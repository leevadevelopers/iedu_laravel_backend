<?php

namespace App\Events\Financial;

use App\Models\V1\Financial\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->invoice->tenant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'invoice.paid';
    }
}

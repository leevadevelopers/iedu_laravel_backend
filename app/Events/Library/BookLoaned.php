<?php

namespace App\Events\Library;

use App\Models\V1\Library\Loan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookLoaned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Loan $loan)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->loan->tenant_id),
            new Channel('user.' . $this->loan->borrower_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'book.loaned';
    }
}

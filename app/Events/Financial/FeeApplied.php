<?php

namespace App\Events\Financial;

use App\Models\V1\Financial\Fee;
use App\Models\V1\Financial\Invoice;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeeApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Fee $fee,
        public User $user,
        public Invoice $invoice
    ) {
    }
}

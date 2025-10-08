<?php

namespace App\Events\Financial;

use App\Models\V1\Financial\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment)
    {
    }
}

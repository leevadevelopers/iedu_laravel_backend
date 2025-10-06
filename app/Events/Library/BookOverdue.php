<?php

namespace App\Events\Library;

use App\Models\V1\Library\Loan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookOverdue
{
    use Dispatchable, SerializesModels;

    public function __construct(public Loan $loan)
    {
    }
}

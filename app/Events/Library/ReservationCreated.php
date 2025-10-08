<?php

namespace App\Events\Library;

use App\Models\V1\Library\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }
}

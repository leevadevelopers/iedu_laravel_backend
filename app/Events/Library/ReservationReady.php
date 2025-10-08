<?php

namespace App\Events\Library;

use App\Models\V1\Library\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('user.' . $this->reservation->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.ready';
    }
}

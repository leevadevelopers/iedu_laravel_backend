<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Events\V1\Transport\StudentCheckedOut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateBusCapacity implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle($event)
    {
        $bus = $event->event->fleetBus;

        if ($event instanceof StudentCheckedIn) {
            // Increment current capacity
            $bus->increment('current_capacity');
        } elseif ($event instanceof StudentCheckedOut) {
            // Decrement current capacity
            $bus->decrement('current_capacity');
        }

        // Ensure capacity doesn't go below 0 or above maximum
        $bus->current_capacity = max(0, min($bus->current_capacity, $bus->capacity));
        $bus->save();
    }
}

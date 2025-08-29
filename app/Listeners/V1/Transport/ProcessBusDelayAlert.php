<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\BusArrivedAtStop;
use App\Jobs\Transport\SendDelayNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessBusDelayAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(BusArrivedAtStop $event)
    {
        // Only process if there's a significant delay (>10 minutes)
        if ($event->getDelayMinutes() <= 10) {
            return;
        }

        // Get all students who use this stop as pickup point
        $subscriptions = StudentTransportSubscription::where('pickup_stop_id', $event->busStop->id)
            ->where('status', 'active')
            ->with('student')
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->authorized_parents) {
                foreach ($subscription->authorized_parents as $parentId) {
                    SendDelayNotification::dispatch([
                        'parent_id' => $parentId,
                        'student_id' => $subscription->student_id,
                        'delay_minutes' => $event->getDelayMinutes(),
                        'stop_name' => $event->busStop->name,
                        'bus_info' => $event->tracking->fleetBus->license_plate,
                        'new_eta' => now()->addMinutes(5)->format('H:i') // Estimated boarding time
                    ]);
                }
            }
        }
    }
}

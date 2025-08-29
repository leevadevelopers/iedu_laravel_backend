<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedIn;
use App\Jobs\Transport\SendTransportNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentCheckinNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentCheckedIn $event)
    {
        $subscription = StudentTransportSubscription::where('student_id', $event->event->student_id)
            ->where('status', 'active')
            ->first();

        if (!$subscription || !$subscription->authorized_parents) {
            return;
        }

        foreach ($subscription->authorized_parents as $parentId) {
            // Send notification via multiple channels
            SendTransportNotification::dispatch([
                'parent_id' => $parentId,
                'student_id' => $event->event->student_id,
                'type' => 'check_in',
                'channels' => ['email', 'sms', 'push'],
                'data' => [
                    'student_name' => $event->event->student->first_name . ' ' . $event->event->student->last_name,
                    'bus_info' => $event->event->fleetBus->license_plate,
                    'stop_name' => $event->event->busStop->name,
                    'time' => $event->event->event_timestamp->format('H:i'),
                    'route_name' => $event->event->transportRoute->name
                ]
            ]);
        }
    }
}

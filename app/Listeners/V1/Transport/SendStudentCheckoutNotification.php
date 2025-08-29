<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\StudentCheckedOut;
use App\Jobs\Transport\SendTransportNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStudentCheckoutNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(StudentCheckedOut $event)
    {
        $subscription = StudentTransportSubscription::where('student_id', $event->event->student_id)
            ->where('status', 'active')
            ->first();

        if (!$subscription || !$subscription->authorized_parents) {
            return;
        }

        foreach ($subscription->authorized_parents as $parentId) {
            SendTransportNotification::dispatch([
                'parent_id' => $parentId,
                'student_id' => $event->event->student_id,
                'type' => 'check_out',
                'channels' => ['email', 'sms', 'push'],
                'data' => [
                    'student_name' => $event->event->student->first_name . ' ' . $event->event->student->last_name,
                    'bus_info' => $event->event->fleetBus->license_plate,
                    'stop_name' => $event->event->busStop->name,
                    'arrival_time' => $event->event->event_timestamp->format('H:i'),
                    'status' => 'Arrived at school safely'
                ]
            ]);
        }
    }
}

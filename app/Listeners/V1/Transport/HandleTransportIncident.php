<?php

namespace App\Listeners\V1\Transport;

use App\Events\V1\Transport\TransportIncidentCreated;
use App\Jobs\Transport\NotifyIncidentStakeholders;
use App\Jobs\Transport\CreateIncidentWorkflow;
use App\Jobs\Transport\SendIncidentNotification;
use App\Models\V1\Transport\StudentTransportSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleTransportIncident implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TransportIncidentCreated $event)
    {
        $incident = $event->incident;

        // Notify relevant stakeholders immediately
        NotifyIncidentStakeholders::dispatch($incident);

        // Create workflow for incident resolution if using Forms Engine
        CreateIncidentWorkflow::dispatch($incident);

        // If critical incident, trigger emergency protocols
        if ($incident->severity === 'critical') {
            $this->handleCriticalIncident($incident);
        }

        // If students are affected, notify their parents
        if (!empty($incident->affected_students)) {
            $this->notifyAffectedParents($incident);
        }
    }

    private function handleCriticalIncident($incident)
    {
        // Implement emergency notification logic
        // Could involve SMS to school admin, emergency services, etc.
    }

    private function notifyAffectedParents($incident)
    {
        foreach ($incident->affected_students as $studentId) {
            $subscription = StudentTransportSubscription::where('student_id', $studentId)
                ->where('status', 'active')
                ->first();

            if ($subscription && $subscription->authorized_parents) {
                foreach ($subscription->authorized_parents as $parentId) {
                    SendIncidentNotification::dispatch([
                        'parent_id' => $parentId,
                        'student_id' => $studentId,
                        'incident_id' => $incident->id,
                        'incident_type' => $incident->incident_type,
                        'severity' => $incident->severity,
                        'description' => $incident->description,
                        'immediate_action' => $incident->immediate_action_taken
                    ]);
                }
            }
        }
    }
}

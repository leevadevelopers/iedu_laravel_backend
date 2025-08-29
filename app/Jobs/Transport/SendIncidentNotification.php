<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\TransportNotification;
use App\Notifications\Transport\TransportIncidentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendIncidentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    protected $notificationData;

    public function __construct(array $notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function handle()
    {
        try {
            $parent = User::findOrFail($this->notificationData['parent_id']);
            $student = Student::findOrFail($this->notificationData['student_id']);

            // Create notification record
            $notification = TransportNotification::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'parent_id' => $parent->id,
                'notification_type' => 'incident',
                'channel' => 'email',
                'subject' => $this->getSubject(),
                'message' => $this->getMessage(),
                'metadata' => $this->notificationData,
                'status' => 'pending'
            ]);

            // Send incident notification
            $parent->notify(new TransportIncidentNotification(
                $notification,
                ['type' => 'parent_notification']
            ));

            $notification->markAsSent();

            Log::info('Incident notification sent to parent', [
                'parent_id' => $parent->id,
                'student_id' => $student->id,
                'incident_id' => $this->notificationData['incident_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send incident notification', [
                'error' => $e->getMessage(),
                'data' => $this->notificationData
            ]);

            if (isset($notification)) {
                $notification->markAsFailed($e->getMessage());
            }

            $this->fail($e);
        }
    }

    private function getSubject(): string
    {
        $severity = ucfirst($this->notificationData['severity']);
        $incidentType = ucwords(str_replace('_', ' ', $this->notificationData['incident_type']));

        return "⚠️ {$severity} {$incidentType} Incident - Immediate Action Required";
    }

    private function getMessage(): string
    {
        $data = $this->notificationData;
        $severity = ucfirst($data['severity']);
        $incidentType = ucwords(str_replace('_', ' ', $data['incident_type']));

        $message = "A {$severity} {$incidentType} incident has occurred involving your child's school bus. ";
        $message .= "Please review the details below and take appropriate action.\n\n";

        $message .= "**Incident Details:**\n";
        $message .= "- Type: {$incidentType}\n";
        $message .= "- Severity: {$severity}\n";
        $message .= "- Description: {$data['description']}\n";

        if (!empty($data['immediate_action'])) {
            $message .= "- Immediate Action Taken: {$data['immediate_action']}\n";
        }

        $message .= "\n**What This Means:**\n";

        if ($data['severity'] === 'critical') {
            $message .= "This is a CRITICAL incident requiring immediate attention. ";
            $message .= "Please contact the school immediately for further instructions.\n";
        } elseif ($data['severity'] === 'high') {
            $message .= "This is a HIGH priority incident. ";
            $message .= "The school will contact you with updates and any required actions.\n";
        } else {
            $message .= "This incident is being handled by school staff. ";
            $message .= "You will receive updates as the situation develops.\n";
        }

        $message .= "\n**Next Steps:**\n";
        $message .= "1. Review the incident details above\n";
        $message .= "2. Contact the school if you have immediate concerns\n";
        $message .= "3. Monitor for additional updates\n";
        $message .= "4. Follow any instructions provided by school staff\n";

        $message .= "\n**Emergency Contact:**\n";
        $message .= "If this is an emergency, please contact emergency services immediately.\n";

        return $message;
    }
}

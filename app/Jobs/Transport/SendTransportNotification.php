<?php

namespace App\Jobs\Transport;

use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\Transport\TransportNotification;
use App\Notifications\Transport\StudentTransportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTransportNotification implements ShouldQueue
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

            $channels = $this->notificationData['channels'] ?? ['email'];

            foreach ($channels as $channel) {
                $this->sendNotificationViaChannel($parent, $student, $channel);
            }

        } catch (\Exception $e) {
            Log::error('Transport notification failed', [
                'error' => $e->getMessage(),
                'data' => $this->notificationData
            ]);

            $this->fail($e);
        }
    }

    private function sendNotificationViaChannel(User $parent, Student $student, string $channel)
    {
        // Create notification record
        $notification = TransportNotification::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'notification_type' => $this->notificationData['type'],
            'channel' => $channel,
            'subject' => $this->getSubject(),
            'message' => $this->getMessage(),
            'metadata' => $this->notificationData['data'],
            'status' => 'pending'
        ]);

        try {
            // Send via Laravel notification system
            $parent->notify(new StudentTransportNotification(
                $notification,
                $channel,
                $this->notificationData
            ));

            $notification->markAsSent();

        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function getSubject(): string
    {
        $studentName = $this->notificationData['data']['student_name'] ?? 'Your child';

        return match($this->notificationData['type']) {
            'check_in' => "✅ {$studentName} boarded the school bus",
            'check_out' => "🏫 {$studentName} arrived at school safely",
            'delay' => "⏰ Bus delay notification for {$studentName}",
            'incident' => "⚠️ Transport incident involving {$studentName}",
            'route_change' => "🛣️ Route change notification for {$studentName}",
            default => "📍 Transport update for {$studentName}"
        };
    }

    private function getMessage(): string
    {
        $data = $this->notificationData['data'];
        $studentName = $data['student_name'] ?? 'Your child';

        return match($this->notificationData['type']) {
            'check_in' => "Good morning! {$studentName} has safely boarded bus {$data['bus_info']} at {$data['stop_name']} at {$data['time']}. The bus is now heading to school.",

            'check_out' => "Great news! {$studentName} has arrived at school at {$data['arrival_time']} and has safely disembarked from bus {$data['bus_info']}.",

            'delay' => "We wanted to let you know that the bus for {$studentName} is running approximately {$data['delay_minutes']} minutes late. New estimated arrival: {$data['new_eta']}.",

            'incident' => "We're writing to inform you of a {$data['incident_type']} incident involving {$studentName}'s school bus. {$data['description']} Immediate action taken: {$data['immediate_action']}. We will keep you updated.",

            'route_change' => "There has been a change to {$studentName}'s bus route. Please check the parent portal for updated information.",

            default => "This is a transport update regarding {$studentName}."
        };
    }

    public function failed(\Throwable $exception)
    {
        Log::error('SendTransportNotification job failed permanently', [
            'exception' => $exception->getMessage(),
            'data' => $this->notificationData
        ]);
    }
}

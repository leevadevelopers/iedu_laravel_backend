<?php

namespace App\Notifications\Transport;

use App\Models\V1\Transport\TransportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\Channels\TwilioChannel;
use App\Notifications\Messages\TwilioSmsMessage;

class StudentTransportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transportNotification;
    protected $channel;
    protected $data;

    public function __construct(TransportNotification $transportNotification, string $channel, array $data)
    {
        $this->transportNotification = $transportNotification;
        $this->channel = $channel;
        $this->data = $data;
    }

    public function via($notifiable)
    {
        $channels = [];

        switch ($this->channel) {
            case 'email':
                $channels[] = 'mail';
                break;
            case 'sms':
                if (config('services.twilio.sid')) {
                    $channels[] = TwilioChannel::class;
                }
                break;
            case 'push':
                $channels[] = 'database';
                $channels[] = 'broadcast';
                break;
            case 'database':
                $channels[] = 'database';
                break;
        }

        return $channels;
    }

    public function toMail($notifiable)
    {
        $data = $this->data['data'] ?? [];
        $studentName = $data['student_name'] ?? 'Your child';

        $message = (new MailMessage)
            ->subject($this->transportNotification->subject)
            ->greeting("Hello {$notifiable->first_name},")
            ->line($this->transportNotification->message);

        // Add action buttons based on notification type
        switch ($this->data['type']) {
            case 'check_in':
                $message->action('View Live Tracking', $this->getTrackingUrl())
                    ->line('You can track the bus location in real-time using the parent portal.');
                break;

            case 'check_out':
                $message->action('View Transport History', $this->getHistoryUrl())
                    ->line('Have a great day at school!');
                break;

            case 'delay':
                $message->line("New estimated arrival: {$data['new_eta']}")
                    ->action('Track Bus Location', $this->getTrackingUrl())
                    ->line('We apologize for any inconvenience caused.');
                break;

            case 'incident':
                $message->line('We will keep you updated as the situation develops.')
                    ->action('Contact School', $this->getContactUrl())
                    ->line('If you have any concerns, please contact us immediately.');
                break;
        }

        $message->line('Thank you for using iEDU Transport Services.')
            ->salutation('Best regards, ' . config('app.name') . ' Transport Team');

        return $message;
    }

    public function toTwilio($notifiable)
    {
        $message = $this->getShortMessage();

        return TwilioSmsMessage::create()
            ->content($message)
            ->from(config('services.twilio.from'));
    }

    public function toDatabase($notifiable)
    {
        return [
            'id' => $this->transportNotification->id,
            'type' => $this->data['type'],
            'title' => $this->transportNotification->subject,
            'message' => $this->transportNotification->message,
            'data' => $this->data['data'],
            'student_id' => $this->data['student_id'],
            'created_at' => now(),
            'actions' => $this->getNotificationActions()
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'id' => $this->transportNotification->id,
            'type' => $this->data['type'],
            'title' => $this->transportNotification->subject,
            'message' => $this->transportNotification->message,
            'data' => $this->data['data'],
            'timestamp' => now()->toISOString()
        ];
    }

    private function getShortMessage(): string
    {
        $data = $this->data['data'] ?? [];
        $studentName = $data['student_name'] ?? 'Your child';

        return match ($this->data['type']) {
            'check_in' => "✅ {$studentName} boarded bus {$data['bus_info']} at {$data['time']}. Track: " . $this->getShortTrackingUrl(),
            'check_out' => "🏫 {$studentName} arrived at school safely at {$data['arrival_time']}.",
            'delay' => "⏰ Bus delay: {$data['delay_minutes']} min. New ETA: {$data['new_eta']}. Track: " . $this->getShortTrackingUrl(),
            'incident' => "⚠️ Transport incident involving {$studentName}. Please contact school for details.",
            default => $this->transportNotification->message
        };
    }

    private function getNotificationActions(): array
    {
        $actions = [];

        switch ($this->data['type']) {
            case 'check_in':
            case 'delay':
                $actions[] = [
                    'title' => 'Track Bus',
                    'url' => $this->getTrackingUrl(),
                    'type' => 'primary'
                ];
                break;

            case 'check_out':
                $actions[] = [
                    'title' => 'View History',
                    'url' => $this->getHistoryUrl(),
                    'type' => 'secondary'
                ];
                break;

            case 'incident':
                $actions[] = [
                    'title' => 'Contact School',
                    'url' => $this->getContactUrl(),
                    'type' => 'danger'
                ];
                break;
        }

        return $actions;
    }

    private function getTrackingUrl(): string
    {
        return config('app.url') . '/parent/transport/student/' . $this->data['student_id'] . '/location';
    }

    private function getHistoryUrl(): string
    {
        return config('app.url') . '/parent/transport/student/' . $this->data['student_id'] . '/history';
    }

    private function getContactUrl(): string
    {
        return config('app.url') . '/contact';
    }

    private function getShortTrackingUrl(): string
    {
        // This would typically be a shortened URL for SMS
        return 'iedu.app/track';
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Transport notification failed', [
            'notification_id' => $this->transportNotification->id,
            'channel' => $this->channel,
            'error' => $exception->getMessage()
        ]);

        // Mark notification as failed
        $this->transportNotification->markAsFailed($exception->getMessage());
    }
    public function toWhatsApp($notifiable)
    {
        return $this->getShortMessage();
    }
}

<?php

namespace App\Notifications\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\TwilioChannel;
use App\Notifications\Messages\TwilioSmsMessage;

class BusDelayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $delayData;

    public function __construct(array $delayData)
    {
        $this->delayData = $delayData;
    }

    public function via($notifiable)
    {
        $channels = ['mail', 'database'];

        // Add SMS if configured and parent prefers it
        if (config('services.twilio.sid') && $this->shouldSendSms($notifiable)) {
            $channels[] = TwilioChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable)
    {
        $delayMinutes = $this->delayData['delay_minutes'];
        $stopName = $this->delayData['stop_name'];
        $busInfo = $this->delayData['bus_info'];
        $newEta = $this->delayData['new_eta'];

        return (new MailMessage)
            ->subject("⏰ Bus Delay Alert - {$delayMinutes} minutes")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We wanted to inform you that the school bus is running approximately {$delayMinutes} minutes behind schedule.")
            ->line("**Pickup Stop:** {$stopName}")
            ->line("**Bus:** {$busInfo}")
            ->line("**New Estimated Arrival:** {$newEta}")
            ->line('We apologize for any inconvenience this may cause.')
            ->action('Track Bus Location', $this->getTrackingUrl())
            ->line('You can monitor the bus location in real-time through the parent portal.')
            ->line('Thank you for your patience.')
            ->salutation('Best regards, iEDU Transport Team');
    }

    public function toTwilio($notifiable)
    {
        $delayMinutes = $this->delayData['delay_minutes'];
        $newEta = $this->delayData['new_eta'];
        $busInfo = $this->delayData['bus_info'];

        $message = "🚌 Bus Delay Alert: Your child's bus ({$busInfo}) is running {$delayMinutes} min late. New ETA: {$newEta}. Track live: iedu.app/track";

        return TwilioSmsMessage::create()
            ->content($message)
            ->from(config('services.twilio.from'));
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'bus_delay',
            'title' => "Bus Delay - {$this->delayData['delay_minutes']} minutes",
            'message' => "The school bus is running {$this->delayData['delay_minutes']} minutes behind schedule.",
            'data' => $this->delayData,
            'actions' => [
                [
                    'title' => 'Track Bus',
                    'url' => $this->getTrackingUrl(),
                    'type' => 'primary'
                ]
            ]
        ];
    }

    private function shouldSendSms($notifiable): bool
    {
        // Check user's notification preferences
        $preferences = $notifiable->transport_notification_preferences ?? [];
        return $preferences['sms_notifications'] ?? false;
    }

    private function getTrackingUrl(): string
    {
        return config('app.url') . '/parent/transport/student/' . $this->delayData['student_id'] . '/location';
    }
}

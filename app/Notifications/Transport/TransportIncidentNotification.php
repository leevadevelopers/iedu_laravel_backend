<?php

namespace App\Notifications\Transport;

use App\Models\V1\Transport\TransportIncident;
use App\Notifications\Channels\TwilioChannel;
use App\Notifications\Messages\TwilioSmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransportIncidentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $incident;
    protected $incidentData;

    public function __construct(TransportIncident $incident, array $incidentData = [])
    {
        $this->incident = $incident;
        $this->incidentData = $incidentData;
    }

    public function via($notifiable)
    {
        $channels = ['mail', 'database'];

        // For critical incidents, always send SMS if available
        if ($this->incident->severity === 'critical' && config('services.twilio.sid')) {
            $channels[] = TwilioChannel::class;
        }

        return $channels;
    }

    public function toMail($notifiable)
    {
        $severity = ucfirst($this->incident->severity);
        $incidentType = ucwords(str_replace('_', ' ', $this->incident->incident_type));

        $message = (new MailMessage)
            ->subject("⚠️ Transport Incident Alert - {$severity}")
            ->greeting("Dear {$notifiable->first_name},");

        if ($this->incident->severity === 'critical') {
            $message->error();
        } elseif ($this->incident->severity === 'high') {
            $message->level('warning');
        }

        $message->line("We are writing to inform you of a transport incident involving your child's school bus.")
                ->line("**Incident Type:** {$incidentType}")
                ->line("**Severity:** {$severity}")
                ->line("**Time:** {$this->incident->incident_datetime->format('Y-m-d H:i')}")
                ->line("**Description:** {$this->incident->description}");

        if ($this->incident->immediate_action_taken) {
            $message->line("**Immediate Action Taken:** {$this->incident->immediate_action_taken}");
        }

        if (in_array($this->incident->severity, ['high', 'critical'])) {
            $message->line('**This incident requires immediate attention.**')
                    ->action('Contact School Immediately', $this->getEmergencyContactUrl());
        } else {
            $message->action('View Incident Details', $this->getIncidentUrl());
        }

        $message->line('We will keep you updated as the situation develops.')
                ->line('If you have any immediate concerns, please contact the school office.')
                ->salutation('iEDU Transport Safety Team');

        return $message;
    }

    public function toTwilio($notifiable)
    {
        $incidentType = str_replace('_', ' ', $this->incident->incident_type);
        $severity = $this->incident->severity;

        $message = "🚨 URGENT: {$severity} {$incidentType} incident on your child's school bus. ";
        $message .= "Immediate action: {$this->incident->immediate_action_taken} ";
        $message .= "Contact school: " . config('school.phone', 'Check parent portal');

        return TwilioSmsMessage::create()
            ->content($message)
            ->from(config('services.twilio.from'));
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'transport_incident',
            'title' => ucwords(str_replace('_', ' ', $this->incident->incident_type)) . ' Incident',
            'message' => $this->incident->description,
            'data' => [
                'incident_id' => $this->incident->id,
                'severity' => $this->incident->severity,
                'incident_type' => $this->incident->incident_type,
                'datetime' => $this->incident->incident_datetime->toISOString(),
                'immediate_action' => $this->incident->immediate_action_taken,
                'bus_info' => [
                    'license_plate' => $this->incident->fleetBus->license_plate,
                    'internal_code' => $this->incident->fleetBus->internal_code
                ]
            ],
            'priority' => $this->getPriority(),
            'actions' => $this->getActions()
        ];
    }

    private function getPriority(): string
    {
        return match($this->incident->severity) {
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'normal',
            'low' => 'low',
            default => 'normal'
        };
    }

    private function getActions(): array
    {
        $actions = [];

        if (in_array($this->incident->severity, ['high', 'critical'])) {
            $actions[] = [
                'title' => 'Contact School',
                'url' => $this->getEmergencyContactUrl(),
                'type' => 'danger'
            ];
        }

        $actions[] = [
            'title' => 'View Details',
            'url' => $this->getIncidentUrl(),
            'type' => 'primary'
        ];

        return $actions;
    }

    private function getIncidentUrl(): string
    {
        return config('app.url') . '/parent/transport/incidents/' . $this->incident->id;
    }

    private function getEmergencyContactUrl(): string
    {
        return config('app.url') . '/contact/emergency';
    }
}

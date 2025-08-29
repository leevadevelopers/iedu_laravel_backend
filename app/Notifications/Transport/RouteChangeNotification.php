<?php

namespace App\Notifications\Transport;

use App\Models\V1\Transport\TransportRoute;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RouteChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $route;
    protected $student;
    protected $changeType;
    protected $changeDetails;

    public function __construct(TransportRoute $route, Student $student, string $changeType, array $changeDetails)
    {
        $this->route = $route;
        $this->student = $student;
        $this->changeType = $changeType;
        $this->changeDetails = $changeDetails;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $studentName = $this->student->first_name . ' ' . $this->student->last_name;
        $changeTypeTitle = ucwords(str_replace('_', ' ', $this->changeType));

        $message = (new MailMessage)
            ->subject("🛣️ Transport Route Change - {$changeTypeTitle}")
            ->greeting("Dear {$notifiable->first_name},")
            ->line("We are writing to inform you of an important change to {$studentName}'s transport route.");

        switch ($this->changeType) {
            case 'stop_change':
                $message->line("**Change Type:** Pickup/Drop-off Stop Change")
                        ->line("**Route:** {$this->route->name}");

                if (isset($this->changeDetails['old_pickup_stop'])) {
                    $message->line("**Old Pickup Stop:** {$this->changeDetails['old_pickup_stop']}")
                            ->line("**New Pickup Stop:** {$this->changeDetails['new_pickup_stop']}");
                }

                if (isset($this->changeDetails['old_dropoff_stop'])) {
                    $message->line("**Old Drop-off Stop:** {$this->changeDetails['old_dropoff_stop']}")
                            ->line("**New Drop-off Stop:** {$this->changeDetails['new_dropoff_stop']}");
                }
                break;

            case 'schedule_change':
                $message->line("**Change Type:** Schedule Change")
                        ->line("**Route:** {$this->route->name}")
                        ->line("**Old Pickup Time:** {$this->changeDetails['old_pickup_time']}")
                        ->line("**New Pickup Time:** {$this->changeDetails['new_pickup_time']}");
                break;

            case 'bus_change':
                $message->line("**Change Type:** Bus Assignment Change")
                        ->line("**Route:** {$this->route->name}")
                        ->line("**Old Bus:** {$this->changeDetails['old_bus']}")
                        ->line("**New Bus:** {$this->changeDetails['new_bus']}");
                break;

            case 'temporary_change':
                $message->line("**Change Type:** Temporary Route Modification")
                        ->line("**Route:** {$this->route->name}")
                        ->line("**Reason:** {$this->changeDetails['reason']}")
                        ->line("**Duration:** {$this->changeDetails['duration']}");
                break;
        }

        $effectiveDate = $this->changeDetails['effective_date'] ?? 'Immediately';
        $message->line("**Effective Date:** {$effectiveDate}")
                ->line($this->changeDetails['reason'] ?? 'This change is part of our ongoing route optimization efforts.')
                ->action('View Updated Route Map', $this->getRouteMapUrl())
                ->line('If you have any questions or concerns, please contact our transport office.')
                ->salutation('iEDU Transport Services');

        return $message;
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'route_change',
            'title' => ucwords(str_replace('_', ' ', $this->changeType)),
            'message' => "Transport route changes for {$this->student->first_name} {$this->student->last_name}",
            'data' => [
                'route_id' => $this->route->id,
                'route_name' => $this->route->name,
                'student_id' => $this->student->id,
                'student_name' => $this->student->first_name . ' ' . $this->student->last_name,
                'change_type' => $this->changeType,
                'change_details' => $this->changeDetails,
                'effective_date' => $this->changeDetails['effective_date'] ?? null
            ],
            'priority' => 'high',
            'actions' => [
                [
                    'title' => 'View Route Map',
                    'url' => $this->getRouteMapUrl(),
                    'type' => 'primary'
                ],
                [
                    'title' => 'Contact Transport Office',
                    'url' => $this->getContactUrl(),
                    'type' => 'secondary'
                ]
            ]
        ];
    }

    private function getRouteMapUrl(): string
    {
        return config('app.url') . '/parent/transport/student/' . $this->student->id . '/route-map';
    }

    private function getContactUrl(): string
    {
        return config('app.url') . '/contact/transport';
    }
}

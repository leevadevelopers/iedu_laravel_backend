#!/bin/bash

# Transport Module - Notifications Generator
echo "📬 Creating Transport Module Notifications..."

# 1. StudentTransportNotification (Multi-channel)
cat > app/Notifications/Transport/StudentTransportNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use App\Models\Transport\TransportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

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

        return match($this->data['type']) {
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
}
EOF

# 2. BusDelayNotification
cat > app/Notifications/Transport/BusDelayNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

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
EOF

# 3. TransportIncidentNotification
cat > app/Notifications/Transport/TransportIncidentNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use App\Models\Transport\TransportIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

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
EOF

# 4. MaintenanceReminderNotification
cat > app/Notifications/Transport/MaintenanceReminderNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use App\Models\Transport\FleetBus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $bus;
    protected $maintenanceType;
    protected $dueDate;

    public function __construct(FleetBus $bus, string $maintenanceType, $dueDate)
    {
        $this->bus = $bus;
        $this->maintenanceType = $maintenanceType;
        $this->dueDate = $dueDate;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $maintenanceType = ucwords(str_replace('_', ' ', $this->maintenanceType));
        $isOverdue = $this->dueDate->isPast();

        $message = (new MailMessage)
            ->subject($isOverdue ? "🚨 OVERDUE: {$maintenanceType} for Bus {$this->bus->license_plate}" : "⚠️ {$maintenanceType} Due for Bus {$this->bus->license_plate}")
            ->greeting("Dear {$notifiable->first_name},");

        if ($isOverdue) {
            $message->error()
                    ->line("The {$maintenanceType} for bus {$this->bus->license_plate} ({$this->bus->internal_code}) is OVERDUE.")
                    ->line("**Due Date:** {$this->dueDate->format('Y-m-d')} ({$this->dueDate->diffForHumans()})")
                    ->line("**IMPORTANT:** This bus has been automatically removed from service until maintenance is completed.");
        } else {
            $message->level('warning')
                    ->line("The {$maintenanceType} for bus {$this->bus->license_plate} ({$this->bus->internal_code}) is due soon.")
                    ->line("**Due Date:** {$this->dueDate->format('Y-m-d')} ({$this->dueDate->diffForHumans()})");
        }

        $message->line("**Bus Details:**")
                ->line("- License Plate: {$this->bus->license_plate}")
                ->line("- Internal Code: {$this->bus->internal_code}")
                ->line("- Make/Model: {$this->bus->make} {$this->bus->model}")
                ->line("- Capacity: {$this->bus->capacity} students")
                ->action('Manage Fleet', $this->getFleetManagementUrl())
                ->line('Please schedule the required maintenance as soon as possible.')
                ->salutation('iEDU Fleet Management System');

        return $message;
    }

    public function toDatabase($notifiable)
    {
        $isOverdue = $this->dueDate->isPast();

        return [
            'type' => 'maintenance_reminder',
            'title' => ($isOverdue ? 'OVERDUE: ' : '') . ucwords(str_replace('_', ' ', $this->maintenanceType)) . ' Due',
            'message' => "Bus {$this->bus->license_plate} requires {$this->maintenanceType}",
            'data' => [
                'bus_id' => $this->bus->id,
                'license_plate' => $this->bus->license_plate,
                'internal_code' => $this->bus->internal_code,
                'maintenance_type' => $this->maintenanceType,
                'due_date' => $this->dueDate->toISOString(),
                'is_overdue' => $isOverdue,
                'days_overdue' => $isOverdue ? $this->dueDate->diffInDays(now()) : null
            ],
            'priority' => $isOverdue ? 'urgent' : 'high',
            'actions' => [
                [
                    'title' => 'Schedule Maintenance',
                    'url' => $this->getMaintenanceScheduleUrl(),
                    'type' => 'primary'
                ],
                [
                    'title' => 'View Bus Details',
                    'url' => $this->getBusDetailsUrl(),
                    'type' => 'secondary'
                ]
            ]
        ];
    }

    private function getFleetManagementUrl(): string
    {
        return config('app.url') . '/transport/fleet';
    }

    private function getMaintenanceScheduleUrl(): string
    {
        return config('app.url') . '/transport/fleet/' . $this->bus->id . '/maintenance';
    }

    private function getBusDetailsUrl(): string
    {
        return config('app.url') . '/transport/fleet/' . $this->bus->id;
    }
}
EOF

# 5. RouteChangeNotification
cat > app/Notifications/Transport/RouteChangeNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use App\Models\Transport\TransportRoute;
use App\Models\Student;
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
EOF

# 6. TransportReportReadyNotification
cat > app/Notifications/Transport/TransportReportReadyNotification.php << 'EOF'
<?php

namespace App\Notifications\Transport;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class TransportReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $reportType;
    protected $filePath;
    protected $parameters;

    public function __construct(string $reportType, string $filePath, array $parameters = [])
    {
        $this->reportType = $reportType;
        $this->filePath = $filePath;
        $this->parameters = $parameters;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $reportTitle = ucwords(str_replace('_', ' ', $this->reportType));
        $fileSize = $this->getFileSize();
        $downloadUrl = $this->getDownloadUrl();

        return (new MailMessage)
            ->subject("📊 Your {$reportTitle} Report is Ready")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your requested {$reportTitle} report has been generated successfully and is ready for download.")
            ->line("**Report Details:**")
            ->line("- Report Type: {$reportTitle}")
            ->line("- Generated: " . now()->format('Y-m-d H:i:s'))
            ->line("- File Size: {$fileSize}")
            ->line("- Format: " . $this->getFileFormat())
            ->action('Download Report', $downloadUrl)
            ->line('The report will be available for download for the next 7 days.')
            ->line('If you have any questions about the report, please contact our support team.')
            ->salutation('iEDU Reporting Team');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'report_ready',
            'title' => ucwords(str_replace('_', ' ', $this->reportType)) . ' Report Ready',
            'message' => "Your {$this->reportType} report has been generated and is ready for download.",
            'data' => [
                'report_type' => $this->reportType,
                'file_path' => $this->filePath,
                'file_size' => $this->getFileSize(),
                'file_format' => $this->getFileFormat(),
                'parameters' => $this->parameters,
                'expires_at' => now()->addDays(7)->toISOString()
            ],
            'actions' => [
                [
                    'title' => 'Download Report',
                    'url' => $this->getDownloadUrl(),
                    'type' => 'primary'
                ]
            ]
        ];
    }

    private function getFileSize(): string
    {
        $bytes = Storage::size($this->filePath);

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function getFileFormat(): string
    {
        return strtoupper(pathinfo($this->filePath, PATHINFO_EXTENSION));
    }

    private function getDownloadUrl(): string
    {
        return config('app.url') . '/transport/reports/download?file=' . urlencode($this->filePath);
    }
}
EOF

# 7. WhatsApp Notification Channel (Optional)
cat > app/Channels/WhatsAppChannel.php << 'EOF'
<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (!$message) {
            return;
        }

        $phone = $this->getPhoneNumber($notifiable);

        if (!$phone) {
            Log::warning('No phone number available for WhatsApp notification', [
                'notifiable_id' => $notifiable->id,
                'notification' => get_class($notification)
            ]);
            return;
        }

        try {
            $this->sendWhatsAppMessage($phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp notification', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'notification' => get_class($notification)
            ]);
        }
    }

    private function getPhoneNumber($notifiable): ?string
    {
        // Try different phone number fields
        return $notifiable->whatsapp_phone ??
               $notifiable->phone ??
               $notifiable->mobile_phone ??
               null;
    }

    private function sendWhatsAppMessage(string $phone, string $message): void
    {
        // Example using Twilio WhatsApp API
        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.token');
        $twilioWhatsAppFrom = config('services.twilio.whatsapp_from');

        if (!$twilioSid || !$twilioWhatsAppFrom) {
            throw new \Exception('WhatsApp/Twilio not configured');
        }

        $response = Http::withBasicAuth($twilioSid, $twilioToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json", [
                'From' => "whatsapp:{$twilioWhatsAppFrom}",
                'To' => "whatsapp:{$phone}",
                'Body' => $message
            ]);

        if (!$response->successful()) {
            throw new \Exception('WhatsApp API error: ' . $response->body());
        }

        Log::info('WhatsApp notification sent successfully', [
            'phone' => $phone,
            'message_length' => strlen($message)
        ]);
    }
}
EOF

# 8. Add WhatsApp methods to existing notifications
cat >> app/Notifications/Transport/StudentTransportNotification.php << 'EOF'

    public function toWhatsApp($notifiable)
    {
        return $this->getShortMessage();
    }
EOF

# 9. Create notification preferences migration
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_add_transport_notification_preferences_to_users.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('transport_notification_preferences')->nullable();
            $table->string('whatsapp_phone', 20)->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['transport_notification_preferences', 'whatsapp_phone']);
        });
    }
};
EOF

echo "✅ Transport module notifications created successfully!"
echo "📬 Notifications include:"
echo "   - Multi-channel student transport notifications (email, SMS, push, WhatsApp)"
echo "   - Real-time bus delay alerts with tracking links"
echo "   - Critical incident notifications with escalation"
echo "   - Maintenance reminders and overdue alerts"
echo "   - Route change notifications with updated maps"
echo "   - Report ready notifications with download links"
echo ""
echo "📱 Supported channels:"
echo "   - Email (with rich HTML templates)"
echo "   - SMS via Twilio"
echo "   - Push notifications (database + broadcasting)"
echo "   - WhatsApp via Twilio (optional)"
echo ""
echo "⚙️ Configuration needed:"
echo "   - Configure mail driver (SMTP, SendGrid, etc.)"
echo "   - Set up Twilio for SMS/WhatsApp (optional)"
echo "   - Configure broadcasting for push notifications"
echo "   - Run migration: php artisan migrate"

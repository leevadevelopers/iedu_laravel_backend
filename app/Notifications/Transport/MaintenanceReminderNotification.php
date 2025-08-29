<?php

namespace App\Notifications\Transport;

use App\Models\V1\Transport\FleetBus;
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

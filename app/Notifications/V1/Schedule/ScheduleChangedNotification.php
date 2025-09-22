<?php

namespace App\Notifications\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Schedule $schedule;
    protected string $changeType;
    protected array $changes;

    public function __construct(Schedule $schedule, string $changeType, array $changes = [])
    {
        $this->schedule = $schedule;
        $this->changeType = $changeType;
        $this->changes = $changes;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add email for significant changes
        if (in_array($this->changeType, ['time_changed', 'cancelled', 'teacher_changed'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $message = new MailMessage();

        $message->subject($subject)
                ->greeting('Olá ' . $notifiable->name)
                ->line($this->getEmailMessage())
                ->line('**Detalhes do Horário:**')
                ->line('Disciplina: ' . $this->schedule->subject->name)
                ->line('Turma: ' . $this->schedule->class->name)
                ->line('Horário: ' . $this->schedule->formatted_time)
                ->line('Dia da semana: ' . $this->schedule->day_of_week_label);

        if (!empty($this->changes)) {
            $message->line('**Alterações:**');
            foreach ($this->changes as $field => $change) {
                $message->line("- {$field}: {$change['from']} → {$change['to']}");
            }
        }

        return $message->action('Ver Horário', url('/schedules/' . $this->schedule->id));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'schedule_changed',
            'change_type' => $this->changeType,
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'subject_name' => $this->schedule->subject->name,
            'class_name' => $this->schedule->class->name,
            'teacher_name' => $this->schedule->teacher->full_name,
            'formatted_time' => $this->schedule->formatted_time,
            'day_of_week' => $this->schedule->day_of_week_label,
            'changes' => $this->changes,
            'message' => $this->getDatabaseMessage()
        ];
    }

    private function getEmailSubject(): string
    {
        switch ($this->changeType) {
            case 'created':
                return 'Novo Horário Adicionado';
            case 'time_changed':
                return 'Alteração de Horário';
            case 'teacher_changed':
                return 'Mudança de Professor';
            case 'cancelled':
                return 'Horário Cancelado';
            case 'room_changed':
                return 'Mudança de Sala';
            default:
                return 'Atualização de Horário';
        }
    }

    private function getEmailMessage(): string
    {
        switch ($this->changeType) {
            case 'created':
                return 'Um novo horário foi adicionado à sua agenda.';
            case 'time_changed':
                return 'O horário de uma de suas aulas foi alterado.';
            case 'teacher_changed':
                return 'Houve mudança no professor de uma de suas disciplinas.';
            case 'cancelled':
                return 'Um horário foi cancelado.';
            case 'room_changed':
                return 'A sala de aula foi alterada.';
            default:
                return 'Houve uma atualização em um de seus horários.';
        }
    }

    private function getDatabaseMessage(): string
    {
        $baseName = $this->schedule->subject->name . ' - ' . $this->schedule->class->name;

        switch ($this->changeType) {
            case 'created':
                return "Novo horário adicionado: {$baseName}";
            case 'time_changed':
                return "Horário alterado: {$baseName} - {$this->schedule->formatted_time}";
            case 'teacher_changed':
                return "Professor alterado em: {$baseName}";
            case 'cancelled':
                return "Horário cancelado: {$baseName}";
            case 'room_changed':
                return "Sala alterada em: {$baseName}";
            default:
                return "Atualização em: {$baseName}";
        }
    }
}

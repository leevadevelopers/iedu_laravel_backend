<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Assessment $assessment
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $daysUntil = now()->diffInDays($this->assessment->scheduled_date);

        return (new MailMessage)
            ->subject('Lembrete: ' . $this->assessment->title)
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('Lembrete de avaliação próxima:')
            ->line('**' . $this->assessment->title . '**')
            ->line('Disciplina: ' . $this->assessment->subject->name)
            ->line('Data: ' . $this->assessment->scheduled_date->format('d/m/Y H:i'))
            ->line('Faltam ' . $daysUntil . ' dia(s)')
            ->action('Ver Detalhes', url('/assessments/' . $this->assessment->id))
            ->line('Prepare-se bem!');
    }

    public function toArray($notifiable): array
    {
        return [
            'assessment_id' => $this->assessment->id,
            'title' => $this->assessment->title,
            'scheduled_date' => $this->assessment->scheduled_date?->toISOString(),
            'type' => 'assessment_reminder',
        ];
    }
}


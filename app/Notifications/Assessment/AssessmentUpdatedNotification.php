<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Assessment $assessment
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Avaliação Atualizada: ' . $this->assessment->title)
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('A avaliação foi atualizada:')
            ->line('**' . $this->assessment->title . '**')
            ->line('Disciplina: ' . $this->assessment->subject->name)
            ->line('Data: ' . $this->assessment->scheduled_date?->format('d/m/Y H:i'))
            ->line('Estado: ' . $this->assessment->status)
            ->action('Ver Detalhes', url('/assessments/' . $this->assessment->id))
            ->line('Por favor, verifique as alterações.');
    }

    public function toArray($notifiable): array
    {
        return [
            'assessment_id' => $this->assessment->id,
            'title' => $this->assessment->title,
            'status' => $this->assessment->status,
            'type' => 'assessment_updated',
        ];
    }
}


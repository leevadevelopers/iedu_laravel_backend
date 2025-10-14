<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentCreatedNotification extends Notification implements ShouldQueue
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
            ->subject('Nova Avaliação: ' . $this->assessment->title)
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('Uma nova avaliação foi criada:')
            ->line('**' . $this->assessment->title . '**')
            ->line('Disciplina: ' . $this->assessment->subject->name)
            ->line('Data: ' . $this->assessment->scheduled_date?->format('d/m/Y H:i'))
            ->line('Nota Total: ' . $this->assessment->total_marks)
            ->when($this->assessment->description, function ($mail) {
                return $mail->line($this->assessment->description);
            })
            ->action('Ver Detalhes', url('/assessments/' . $this->assessment->id))
            ->line('Boa sorte!');
    }

    public function toArray($notifiable): array
    {
        return [
            'assessment_id' => $this->assessment->id,
            'title' => $this->assessment->title,
            'scheduled_date' => $this->assessment->scheduled_date?->toISOString(),
            'subject' => $this->assessment->subject->name,
            'type' => 'assessment_created',
        ];
    }
}


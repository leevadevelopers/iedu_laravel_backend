<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\GradeReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeReviewRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GradeReview $gradeReview
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $gradeEntry = $this->gradeReview->gradeEntry;
        $assessment = $gradeEntry->assessment;
        $student = $this->gradeReview->requester;

        return (new MailMessage)
            ->subject('Pedido de Revisão de Nota')
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('Um aluno solicitou revisão de nota:')
            ->line('**Aluno:** ' . $student->name)
            ->line('**Avaliação:** ' . $assessment->title)
            ->line('**Nota Atual:** ' . $gradeEntry->marks_awarded)
            ->line('**Motivo:** ' . $this->gradeReview->reason)
            ->action('Ver Pedido', url('/grade-reviews/' . $this->gradeReview->id))
            ->line('Por favor, analise o pedido.');
    }

    public function toArray($notifiable): array
    {
        return [
            'grade_review_id' => $this->gradeReview->id,
            'requester_id' => $this->gradeReview->requester_id,
            'grade_entry_id' => $this->gradeReview->grade_entry_id,
            'reason' => $this->gradeReview->reason,
            'type' => 'grade_review_requested',
        ];
    }
}


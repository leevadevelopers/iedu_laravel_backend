<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\GradeReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradeReviewResolvedNotification extends Notification implements ShouldQueue
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

        $statusText = match($this->gradeReview->status) {
            'accepted' => 'aceite',
            'rejected' => 'rejeitado',
            'resolved' => 'resolvido',
            default => $this->gradeReview->status,
        };

        return (new MailMessage)
            ->subject('Pedido de Revisão de Nota - ' . ucfirst($statusText))
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('O seu pedido de revisão de nota foi ' . $statusText . ':')
            ->line('**Avaliação:** ' . $assessment->title)
            ->line('**Estado:** ' . ucfirst($statusText))
            ->when($this->gradeReview->revised_marks, function ($mail) {
                return $mail->line('**Nova Nota:** ' . $this->gradeReview->revised_marks);
            })
            ->when($this->gradeReview->reviewer_comments, function ($mail) {
                return $mail->line('**Comentários:** ' . $this->gradeReview->reviewer_comments);
            })
            ->action('Ver Detalhes', url('/grade-reviews/' . $this->gradeReview->id))
            ->line('Obrigado!');
    }

    public function toArray($notifiable): array
    {
        return [
            'grade_review_id' => $this->gradeReview->id,
            'status' => $this->gradeReview->status,
            'revised_marks' => $this->gradeReview->revised_marks,
            'reviewer_comments' => $this->gradeReview->reviewer_comments,
            'type' => 'grade_review_resolved',
        ];
    }
}


<?php

namespace App\Notifications\Assessment;

use App\Models\Assessment\Assessment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GradesPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Assessment $assessment,
        public User $student
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $gradeEntry = $this->assessment->gradeEntries()
            ->where('student_id', $this->student->id)
            ->first();

        return (new MailMessage)
            ->subject('Notas Publicadas: ' . $this->assessment->title)
            ->greeting('Olá ' . $notifiable->name . '!')
            ->line('As notas da avaliação foram publicadas:')
            ->line('**' . $this->assessment->title . '**')
            ->line('Disciplina: ' . $this->assessment->subject->name)
            ->when($gradeEntry, function ($mail) use ($gradeEntry) {
                return $mail->line('Nota: ' . $gradeEntry->marks_awarded . ' / ' . $this->assessment->total_marks);
            })
            ->action('Ver Nota', url('/grades/' . $this->assessment->id))
            ->line('Parabéns pelo seu esforço!');
    }

    public function toArray($notifiable): array
    {
        $gradeEntry = $this->assessment->gradeEntries()
            ->where('student_id', $this->student->id)
            ->first();

        return [
            'assessment_id' => $this->assessment->id,
            'assessment_title' => $this->assessment->title,
            'marks_awarded' => $gradeEntry?->marks_awarded,
            'total_marks' => $this->assessment->total_marks,
            'type' => 'grades_published',
        ];
    }
}


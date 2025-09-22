<?php

namespace App\Notifications\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Lesson $lesson;
    protected string $eventType;
    protected array $additionalData;

    public function __construct(Lesson $lesson, string $eventType, array $additionalData = [])
    {
        $this->lesson = $lesson;
        $this->eventType = $eventType;
        $this->additionalData = $additionalData;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = new MailMessage();

        return $message
            ->subject($this->getEmailSubject())
            ->greeting('Olá ' . $notifiable->name)
            ->line($this->getEmailMessage())
            ->line('**Detalhes da Aula:**')
            ->line('Título: ' . $this->lesson->title)
            ->line('Disciplina: ' . $this->lesson->subject->name)
            ->line('Data: ' . $this->lesson->lesson_date->format('d/m/Y'))
            ->line('Horário: ' . $this->lesson->formatted_time)
            ->when($this->lesson->is_online, function ($message) {
                return $message->line('**Aula Online**')
                               ->action('Entrar na Reunião', $this->lesson->online_meeting_url);
            })
            ->when(!$this->lesson->is_online, function ($message) {
                return $message->line('Sala: ' . ($this->lesson->classroom ?? 'A definir'));
            })
            ->action('Ver Aula', url('/lessons/' . $this->lesson->id));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'lesson_notification',
            'event_type' => $this->eventType,
            'lesson_id' => $this->lesson->id,
            'lesson_title' => $this->lesson->title,
            'subject_name' => $this->lesson->subject->name,
            'class_name' => $this->lesson->class->name,
            'lesson_date' => $this->lesson->lesson_date->format('Y-m-d'),
            'formatted_time' => $this->lesson->formatted_time,
            'is_online' => $this->lesson->is_online,
            'classroom' => $this->lesson->classroom,
            'status' => $this->lesson->status,
            'message' => $this->getDatabaseMessage(),
            'additional_data' => $this->additionalData
        ];
    }

    private function getEmailSubject(): string
    {
        switch ($this->eventType) {
            case 'lesson_starting':
                return 'Aula Iniciando em Breve';
            case 'lesson_cancelled':
                return 'Aula Cancelada';
            case 'lesson_completed':
                return 'Aula Concluída';
            case 'content_added':
                return 'Novo Conteúdo Adicionado';
            case 'homework_assigned':
                return 'Atividade Atribuída';
            case 'attendance_marked':
                return 'Presença Registrada';
            default:
                return 'Atualização da Aula';
        }
    }

    private function getEmailMessage(): string
    {
        switch ($this->eventType) {
            case 'lesson_starting':
                return 'Uma de suas aulas começará em breve.';
            case 'lesson_cancelled':
                return 'Uma aula foi cancelada.';
            case 'lesson_completed':
                return 'A aula foi concluída com sucesso.';
            case 'content_added':
                return 'Novo material foi adicionado à aula.';
            case 'homework_assigned':
                return 'Uma nova atividade foi atribuída.';
            case 'attendance_marked':
                return 'Sua presença na aula foi registrada.';
            default:
                return 'Há uma atualização sobre uma de suas aulas.';
        }
    }

    private function getDatabaseMessage(): string
    {
        $lessonInfo = $this->lesson->subject->name . ' - ' . $this->lesson->lesson_date->format('d/m/Y');

        switch ($this->eventType) {
            case 'lesson_starting':
                return "Aula iniciando: {$lessonInfo}";
            case 'lesson_cancelled':
                return "Aula cancelada: {$lessonInfo}";
            case 'lesson_completed':
                return "Aula concluída: {$lessonInfo}";
            case 'content_added':
                return "Novo conteúdo em: {$lessonInfo}";
            case 'homework_assigned':
                return "Atividade atribuída em: {$lessonInfo}";
            case 'attendance_marked':
                return "Presença registrada em: {$lessonInfo}";
            default:
                return "Atualização em: {$lessonInfo}";
        }
    }
}

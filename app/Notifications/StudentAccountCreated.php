<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StudentAccountCreated extends Notification
{
    use Queueable;

    protected $temporaryPassword;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $temporaryPassword)
    {
        $this->temporaryPassword = $temporaryPassword;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to iEDU - Your Account is Ready')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your student account has been created in the iEDU system.')
            ->line('You can now log in using the following credentials:')
            ->line('**Login:** ' . $notifiable->identifier)
            ->line('**Temporary Password:** ' . $this->temporaryPassword)
            ->line('**Important:** You will be required to change your password on first login for security.')
            ->action('Login to iEDU', url('/login'))
            ->line('If you have any questions, please contact the school administration.')
            ->line('Thank you for joining us!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Your student account has been created',
            'login' => $notifiable->identifier,
        ];
    }
}

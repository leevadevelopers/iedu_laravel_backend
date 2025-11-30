<?php

namespace App\Mail;

use App\Models\User;
use App\Models\V1\Academic\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeacherWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $teacher;
    public $user;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct(Teacher $teacher, User $user, string $password)
    {
        $this->teacher = $teacher;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo ao iEDU - Portal do Professor',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.teacher-welcome',
            with: [
                'teacher' => $this->teacher,
                'user' => $this->user,
                'password' => $this->password,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}


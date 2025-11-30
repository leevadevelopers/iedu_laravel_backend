<?php

namespace App\Mail;

use App\Models\User;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParentStudentRegisteredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $student;
    public $guardian;
    public $relationship;

    /**
     * Create a new message instance.
     */
    public function __construct(Student $student, User $guardian, FamilyRelationship $relationship)
    {
        $this->student = $student;
        $this->guardian = $guardian;
        $this->relationship = $relationship;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $studentName = $this->student->first_name . ' ' . $this->student->last_name;
        return new Envelope(
            subject: "Cadastro de {$studentName} no iEDU",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.parent-student-registered',
            with: [
                'student' => $this->student,
                'guardian' => $this->guardian,
                'relationship' => $this->relationship,
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


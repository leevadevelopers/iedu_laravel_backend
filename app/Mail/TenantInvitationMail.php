<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\TenantInvitation;
use App\Models\Settings\Tenant;
use App\Models\User;

class TenantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $tenant;
    public $inviter;
    public $roleDisplayName;
    public $acceptUrl;
    public $expiryDate;

    /**
     * Create a new message instance.
     */
    public function __construct(
        TenantInvitation $invitation,
        Tenant $tenant,
        User $inviter,
        string $roleDisplayName,
        string $acceptUrl,
        string $expiryDate
    ) {
        $this->invitation = $invitation;
        $this->tenant = $tenant;
        $this->inviter = $inviter;
        $this->roleDisplayName = $roleDisplayName;
        $this->acceptUrl = $acceptUrl;
        $this->expiryDate = $expiryDate;
        
        // Log email construction for debugging
        \Log::info('TenantInvitationMail constructed', [
            'invitation_id' => $invitation->id,
            'tenant_name' => $tenant->name,
            'inviter_name' => $inviter->name,
            'role_display_name' => $roleDisplayName,
            'accept_url' => $acceptUrl,
            'expiry_date' => $expiryDate,
            'environment' => app()->environment()
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = "Convite para Equipe - {$this->tenant->name}";
        
        Log::info('TenantInvitationMail envelope created', [
            'invitation_id' => $this->invitation->id,
            'subject' => $subject,
            'tenant_name' => $this->tenant->name
        ]);
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        Log::info('TenantInvitationMail content created', [
            'invitation_id' => $this->invitation->id,
            'view' => 'emails.tenant-invitation',
            'accept_url' => $this->acceptUrl,
            'role_display_name' => $this->roleDisplayName
        ]);
        
        return new Content(
            view: 'emails.tenant-invitation',
            with: [
                'invitation' => $this->invitation,
                'tenant' => $this->tenant,
                'inviter' => $this->inviter,
                'roleDisplayName' => $this->roleDisplayName,
                'acceptUrl' => $this->acceptUrl,
                'expiryDate' => $this->expiryDate,
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

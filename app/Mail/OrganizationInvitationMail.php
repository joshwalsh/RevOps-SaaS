<?php

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class OrganizationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(public OrganizationInvitation $invitation)
    {
        $this->acceptUrl = URL::temporarySignedRoute(
            'organizations.invitations.accept',
            now()->addDays(7),
            ['invitation' => $invitation->id],
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->organization->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organizations.invitation',
            with: [
                'organizationName' => $this->invitation->organization->name,
                'acceptUrl' => $this->acceptUrl,
            ],
        );
    }
}

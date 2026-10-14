<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Support\Roles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public string $token)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Undangan bergabung di PenpenHub');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'url' => route('invitations.show', ['token' => $this->token]),
                'roleLabel' => Roles::label($this->invitation->role),
                'companyName' => $this->invitation->company->name,
                'inviterName' => $this->invitation->inviter->name,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}

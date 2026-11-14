<?php

namespace App\Mail;

use App\Models\Branch;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BranchManagerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken,
        public Branch $branch
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Official Invitation to Join PawHaven as Branch Manager',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.branch-manager-invitation',
        );
    }
}

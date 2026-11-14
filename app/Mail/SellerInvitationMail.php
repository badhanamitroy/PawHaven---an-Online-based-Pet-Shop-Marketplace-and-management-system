<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Official Invitation to Join PawHaven as a Seller',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.seller-invitation',
        );
    }
}

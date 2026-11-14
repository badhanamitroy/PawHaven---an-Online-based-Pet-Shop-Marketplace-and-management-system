<?php

namespace App\Mail;

use App\Models\EmploymentOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmploymentOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmploymentOffer $offer, public string $rawToken) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'PawHaven employment offer');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.careers.offer');
    }
}

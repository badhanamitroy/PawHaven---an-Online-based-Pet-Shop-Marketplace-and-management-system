<?php

namespace App\Mail;

use App\Models\PetListing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ListingModeratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PetListing $listing,
        public bool $isApproved,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->isApproved ? 'Approved' : 'Review Update';

        return new Envelope(
            subject: 'Listing '.$status.': '.$this->listing->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketplace.listing-moderated',
        );
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $activityTitle,
        public string $activityDescription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security Alert: '.$this->activityTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.security-alert',
        );
    }
}

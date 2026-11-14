<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PendingEmployeeApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $employeeName, public string $position, public string $branchName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Employee access approval required');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.careers.pending-approval');
    }
}

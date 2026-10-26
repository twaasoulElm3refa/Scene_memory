<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountBannedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly mixed $user,
        public readonly string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Restriction Notice',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account_banned',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

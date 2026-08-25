<?php

namespace App\Mail;

use App\Models\SpecialCoverageRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpecialCoverageRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SpecialCoverageRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تعذر قبول طلب التغطية الخاصة',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.special-coverage-rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

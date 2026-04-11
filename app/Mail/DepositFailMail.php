<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositFailMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $amount;
    public $name;

    public function __construct($amount, $name)
    {
        $this->amount = $amount;
        $this->name = $name;
    }

    /**
     * Subject + meta
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '❌ فشل في شحن الرصيد',
        );
    }

    /**
     * View + data
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.deposit_fail',
            with: [
                'amount' => number_format($this->amount, 2),
                'name'   => $this->name,
            ],
        );
    }

    /**
     * Attachments (لو محتاج)
     */
    public function attachments(): array
    {
        return [];
    }
}

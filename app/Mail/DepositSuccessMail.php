<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositSuccessMail extends Mailable implements ShouldQueue
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
     * Email subject + meta
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ تم شحن رصيدك بنجاح',
        );
    }

    /**
     * View + data
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.deposit_success',
            with: [
                'amount' => $this->amount,
                'name'   => $this->name,
            ],
        );
    }

    /**
     * Attachments (optional)
     */
    public function attachments(): array
    {
        return [];
    }
}

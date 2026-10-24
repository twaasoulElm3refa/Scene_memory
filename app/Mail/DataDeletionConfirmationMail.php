<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DataDeletionConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $reference
    ) {}

    public function build(): self
    {
        return $this->subject('Scemory Account & Data Deletion Confirmation Code')
            ->markdown('mail.data-deletion-confirmation');
    }
}

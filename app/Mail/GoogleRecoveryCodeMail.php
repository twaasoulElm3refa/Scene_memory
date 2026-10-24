<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GoogleRecoveryCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $code) {}

    public function build(): self
    {
        return $this->subject('Scemory Google account recovery code')
            ->markdown('mail.google-recovery-code');
    }
}

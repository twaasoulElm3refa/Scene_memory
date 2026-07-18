<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp
    ) {}

    public function build(): self
    {
        return $this->subject('Scemory email verification code')
            ->markdown('mail.register-otp');
    }
}

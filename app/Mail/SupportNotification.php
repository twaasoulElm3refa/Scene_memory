<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $mailSubject, public string $body)
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject($this->mailSubject)->text('mail.support-notification');
    }
}

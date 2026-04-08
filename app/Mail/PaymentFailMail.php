<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailMail extends Mailable
{
    use Queueable, SerializesModels;

    public $amount;
    public $userName;

    public function __construct($amount, $userName)
    {
        $this->amount = $amount;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject('Payment Failed')
            ->view('mail.payment-fail');
    }
}

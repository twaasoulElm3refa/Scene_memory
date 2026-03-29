<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $license;

    public function __construct(User $user, $license)
    {
        $this->user = $user;
        $this->license = $license;
    }

    public function build()
    {
        return $this->subject('🎉 Subscription Activated Successfully')
            ->view('mail.subscription-success');
    }
}

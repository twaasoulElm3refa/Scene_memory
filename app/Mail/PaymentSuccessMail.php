<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\LicenseService;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function build()
    {
        $token = LicenseService::generateToken($this->order);

        $pdf = Pdf::loadView('pdf.license', [
            'order' => $this->order,
            'token' => $token,
        ])->output();
        return $this->subject('Payment Successful - Your License')
            ->view('mail.payment-success')
            ->attachData($pdf, 'license.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}

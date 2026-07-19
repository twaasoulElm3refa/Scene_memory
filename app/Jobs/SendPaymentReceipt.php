<?php

namespace App\Jobs;

use App\Mail\DepositSuccessMail;
use App\Mail\PaymentSuccessMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPaymentReceipt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::with(['order.user'])->find($this->paymentId);
        if (! $payment || $payment->status !== 'completed' || $payment->order->mail_sent || ! $payment->order->user?->email) {
            return;
        }

        try {
            $mailable = $payment->operation === 'wallet_deposit'
                ? new DepositSuccessMail($payment->order->amount, $payment->order->user->name)
                : new PaymentSuccessMail($payment->order);

            Mail::to($payment->order->user->email)->send($mailable);
            $payment->order->update(['mail_sent' => true]);
            Log::info('email_queued', ['payment_id' => $payment->id, 'order_id' => $payment->order_id]);
        } catch (Throwable $exception) {
            Log::error('email_failed', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception' => $exception::class,
            ]);
            throw $exception;
        }
    }
}

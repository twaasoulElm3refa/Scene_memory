<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Preserve settled funds and entitlements until a documented reversal policy is approved. */
class PayPalReversalReview
{
    public function __construct(private readonly PayPalOrderResolver $resolver) {}

    public function flag(array $payload): string
    {
        $payment = $this->resolver->resolve($payload, false);
        $resource = $payload['resource'] ?? [];
        $captureId = data_get($resource, 'supplementary_data.related_ids.capture_id')
            ?? data_get($resource, 'disputed_transactions.0.seller_transaction_id');
        if (! $payment || $payment->method !== 'paypal' || $payment->operation === 'virtual_gift'
            || $payment->status !== 'completed' || ! $payment->capture_id
            || ! is_string($captureId) || ! hash_equals($payment->capture_id, $captureId)) {
            return 'ignored';
        }

        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'completed') {
                $locked->forceFill([
                    'verification_review_required' => true,
                    'verification_error_category' => 'reversal_review',
                    'next_verification_at' => null,
                ])->save();
            }
        }, 5);
        Log::warning('paypal_reversal_review_required', [
            'payment_id' => $payment->id,
            'provider_order_id' => $payment->paypal_order_id,
            'event_id' => $payload['id'] ?? null,
            'event_type' => $payload['event_type'] ?? null,
        ]);

        return 'ignored';
    }
}

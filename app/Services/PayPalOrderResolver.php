<?php

namespace App\Services;

use App\Models\Payment;

class PayPalOrderResolver
{
    public function __construct(private readonly PayPalCaptureData $captureData) {}

    public function resolve(array $payload, bool $lockForUpdate = true): ?Payment
    {
        $resource = $payload['resource'] ?? [];
        $query = Payment::query();
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $paypalOrderId = $this->captureData->extractPaypalOrderId($resource);
        if (! $paypalOrderId && ($payload['event_type'] ?? null) === 'CHECKOUT.ORDER.APPROVED') {
            $paypalOrderId = $resource['id'] ?? null;
        }
        if ($paypalOrderId && $payment = (clone $query)->where('paypal_order_id', $paypalOrderId)->first()) {
            return $payment;
        }

        $customId = $this->captureData->extractCustomId($resource);
        if (is_string($customId) && preg_match('/^(?:purchase|wallet_topup):(\d+)$/', $customId, $matches)) {
            if ($payment = (clone $query)->find((int) $matches[1])) {
                return $payment;
            }
        }

        $captureId = $this->captureData->extractCaptureId($resource)
            ?? data_get($resource, 'supplementary_data.related_ids.capture_id');

        return $captureId ? (clone $query)->where('capture_id', $captureId)->first() : null;
    }
}

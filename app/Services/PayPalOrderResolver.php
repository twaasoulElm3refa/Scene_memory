<?php

namespace App\Services;

use App\Models\Purchases;

class PayPalOrderResolver
{
    public function resolve(array $payload, bool $lockForUpdate = true): ?Purchases
    {
        $resource = $payload['resource'] ?? [];
        $eventType = $payload['event_type'] ?? null;

        $paypalOrderId = data_get($resource, 'supplementary_data.related_ids.order_id');

        if (! $paypalOrderId && $eventType === 'CHECKOUT.ORDER.APPROVED') {
            $paypalOrderId = $resource['id'] ?? null;
        }

        if ($paypalOrderId) {
            $order = $this->query($lockForUpdate)
                ->where('paypal_order_id', $paypalOrderId)
                ->first();

            if ($order) {
                return $order;
            }
        }

        $customId = $resource['custom_id']
            ?? data_get($resource, 'purchase_units.0.custom_id');
        $referenceId = $resource['reference_id']
            ?? data_get($resource, 'purchase_units.0.reference_id');
        $localId = $this->localId($customId) ?? $this->localId($referenceId);

        if ($localId) {
            $order = $this->query($lockForUpdate)->whereKey($localId)->first();

            if ($order) {
                return $order;
            }
        }

        $captureId = data_get($resource, 'supplementary_data.related_ids.capture_id');

        if ($captureId) {
            return $this->query($lockForUpdate)
                ->where('transaction_id', $captureId)
                ->first();
        }

        return null;
    }

    private function query(bool $lockForUpdate)
    {
        $query = Purchases::query();

        return $lockForUpdate ? $query->lockForUpdate() : $query;
    }

    private function localId(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^(?:checkout|wallet_topup):(\d+)$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}

<?php

namespace App\Services;

use App\Models\Purchases;
use RuntimeException;

class PayPalAmountValidator
{
    public function assertMatches(Purchases $order, array $resource): void
    {
        $amount = $this->amount($resource);

        if (! $amount) {
            throw new RuntimeException('PayPal amount is missing from the event.');
        }

        $expectedValue = number_format((float) $order->amount, 2, '.', '');
        $actualValue = number_format((float) ($amount['value'] ?? -1), 2, '.', '');
        $expectedCurrency = strtoupper((string) $order->currency);
        $actualCurrency = strtoupper((string) ($amount['currency_code'] ?? ''));

        if ($expectedValue !== $actualValue || $expectedCurrency !== $actualCurrency) {
            throw new RuntimeException('PayPal amount or currency does not match the local order.');
        }
    }

    public function captureId(array $resource): ?string
    {
        return data_get($resource, 'purchase_units.0.payments.captures.0.id')
            ?? ($this->looksLikeCapture($resource) ? ($resource['id'] ?? null) : null);
    }

    private function amount(array $resource): ?array
    {
        return $resource['amount']
            ?? data_get($resource, 'purchase_units.0.payments.captures.0.amount')
            ?? data_get($resource, 'purchase_units.0.amount');
    }

    private function looksLikeCapture(array $resource): bool
    {
        return isset($resource['supplementary_data']['related_ids']['order_id']);
    }
}

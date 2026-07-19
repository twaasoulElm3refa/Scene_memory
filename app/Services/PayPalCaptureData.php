<?php

namespace App\Services;

class PayPalCaptureData
{
    public function __construct(private readonly MinorMoney $money) {}

    public function extractPaypalOrderId(array $resource): ?string
    {
        return data_get($resource, 'supplementary_data.related_ids.order_id')
            ?? (isset($resource['purchase_units']) ? ($resource['id'] ?? null) : null);
    }

    public function extractCaptureId(array $resource): ?string
    {
        return data_get($resource, 'purchase_units.0.payments.captures.0.id')
            ?? (data_get($resource, 'supplementary_data.related_ids.order_id') ? ($resource['id'] ?? null) : null);
    }

    public function extractAmountMinor(array $resource): ?int
    {
        $value = data_get($resource, 'amount.value')
            ?? data_get($resource, 'purchase_units.0.payments.captures.0.amount.value')
            ?? data_get($resource, 'purchase_units.0.amount.value');

        return $value === null ? null : $this->money->fromDecimal((string) $value);
    }

    public function extractCurrency(array $resource): ?string
    {
        $currency = data_get($resource, 'amount.currency_code')
            ?? data_get($resource, 'purchase_units.0.payments.captures.0.amount.currency_code')
            ?? data_get($resource, 'purchase_units.0.amount.currency_code');

        return $currency ? strtoupper((string) $currency) : null;
    }

    public function extractCustomId(array $resource): ?string
    {
        return $resource['custom_id'] ?? data_get($resource, 'purchase_units.0.custom_id');
    }

    public function extractReferenceId(array $resource): ?string
    {
        return $resource['reference_id'] ?? data_get($resource, 'purchase_units.0.reference_id');
    }

    public function extractMerchantId(array $resource): ?string
    {
        return data_get($resource, 'payee.merchant_id')
            ?? data_get($resource, 'purchase_units.0.payee.merchant_id')
            ?? data_get($resource, 'seller_receivable_breakdown.paypal_fee.payee.merchant_id');
    }

    public function extractCaptureStatus(array $resource): ?string
    {
        return data_get($resource, 'purchase_units.0.payments.captures.0.status')
            ?? ($this->extractCaptureId($resource) ? ($resource['status'] ?? null) : null);
    }
}

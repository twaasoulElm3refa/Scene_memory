<?php

namespace App\Services;

use App\Models\Payment;
use UnexpectedValueException;

class PaymentFinancialValidator
{
    public function __construct(private readonly PayPalCaptureData $captureData) {}

    public function assertCompletedCapture(Payment $payment, array $resource, bool $reversedGiftEvidence = false): array
    {
        $order = $payment->order;
        if (! $order || $order->user_id !== $payment->user_id || ! $order->user()->exists()) {
            throw new UnexpectedValueException('Payment user or order is invalid.');
        }
        if ($order->items()->whereNotNull('image_id')->count() < 1 && $payment->operation === 'purchase') {
            throw new UnexpectedValueException('Order snapshot is empty.');
        }

        $status = strtoupper((string) $this->captureData->extractCaptureStatus($resource));
        $allowed = $reversedGiftEvidence && $payment->operation === 'virtual_gift' ? ['COMPLETED', 'REFUNDED', 'PARTIALLY_REFUNDED'] : ['COMPLETED'];
        if (! in_array($status, $allowed, true)) {
            throw new UnexpectedValueException('PayPal capture is not completed.');
        }

        $paypalOrderId = $this->captureData->extractPaypalOrderId($resource);
        if (! $paypalOrderId || ! hash_equals((string) $payment->paypal_order_id, $paypalOrderId)) {
            throw new UnexpectedValueException('PayPal order ID does not match the payment.');
        }

        $captureId = $this->captureData->extractCaptureId($resource);
        if (! $captureId) {
            throw new UnexpectedValueException('PayPal capture ID is missing.');
        }
        if (Payment::query()->where('capture_id', $captureId)->whereKeyNot($payment->id)->exists()) {
            throw new UnexpectedValueException('PayPal capture ID was already used.');
        }

        $amountMinor = $this->captureData->extractAmountMinor($resource);
        $currency = $this->captureData->extractCurrency($resource);
        if ($amountMinor !== (int) $payment->amount_minor || $currency !== strtoupper($payment->currency)) {
            throw new UnexpectedValueException('PayPal amount or currency does not match the payment.');
        }

        $customId = $this->captureData->extractCustomId($resource);
        if (! $customId || ! hash_equals((string) $payment->custom_id, $customId)) {
            throw new UnexpectedValueException('PayPal custom ID does not match the payment.');
        }

        $referenceId = $this->captureData->extractReferenceId($resource);
        if ($referenceId !== null && ! hash_equals((string) $payment->reference_id, $referenceId)) {
            throw new UnexpectedValueException('PayPal reference ID does not match the payment.');
        }

        $expectedMerchant = (string) config('paypal.merchant_id');
        $merchantId = $this->captureData->extractMerchantId($resource);
        if ($expectedMerchant === '' || ! $merchantId || ! hash_equals($expectedMerchant, $merchantId)) {
            throw new UnexpectedValueException('PayPal merchant ID is missing or does not match.');
        }

        return compact('captureId', 'paypalOrderId', 'amountMinor', 'currency', 'customId', 'referenceId', 'merchantId');
    }
}

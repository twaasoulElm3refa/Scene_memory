<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/** Independently checks a locally created PayPal order before using existing settlement paths. */
class PayPalPaymentVerification
{
    private const MAX_ATTEMPTS = 6;

    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly PayPalServices $purchases,
        private readonly PayPalWalletServices $deposits,
        private readonly MinorMoney $money,
    ) {}

    public function verify(Payment $payment, string $trigger = 'status'): string
    {
        $claimed = DB::transaction(function () use ($payment): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->verification_attempts >= self::MAX_ATTEMPTS && ! $locked->verification_review_required) {
                $locked->forceFill([
                    'verification_review_required' => true,
                    'verification_error_category' => 'retry_exhausted',
                    'verification_lease_until' => null,
                    'next_verification_at' => null,
                ])->save();
            }
            if ($locked->method !== 'paypal' || ! in_array($locked->operation, ['purchase', 'wallet_deposit', 'plan_subscription', 'virtual_gift'], true)
                || ! in_array($locked->status, ['pending', 'approved', 'failed'], true)
                || $locked->verification_review_required || $locked->verification_attempts >= self::MAX_ATTEMPTS
                || $locked->verification_lease_until?->isFuture() || $locked->next_verification_at?->isFuture()) {
                return false;
            }

            $locked->forceFill([
                'verification_attempts' => $locked->verification_attempts + 1,
                'last_verification_at' => now(),
                'verification_lease_until' => now()->addSeconds(90),
                'next_verification_at' => null,
                'verification_error_category' => null,
            ])->save();

            return true;
        }, 5);

        if (! $claimed) {
            return $payment->fresh()->status === 'completed' ? 'completed' : 'pending';
        }

        $phase = 'lookup';
        $providerResponseCategory = null;
        try {
            $payment = $payment->fresh('order');
            if (! filled(config('paypal.merchant_id'))) {
                throw new PayPalConfigurationException('PayPal merchant ID is not configured.');
            }
            if (! $payment->paypal_order_id) {
                throw new UnexpectedValueException('Missing bound PayPal order.');
            }

            $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
            if (! isset($details['id'])) {
                $providerResponseCategory = $this->responseErrorCategory($details);
                if ($providerResponseCategory === 'provider_incomplete_response') {
                    throw new IncompletePayPalEvidenceException('PayPal order evidence is incomplete.');
                }
                throw new RuntimeException('PayPal order lookup failed.');
            }
            $status = $this->validateOrder($payment, $details);
            $phase = 'settlement';

            if ($status === 'VOIDED') {
                if ($payment->operation === 'virtual_gift') {
                    $this->purchases->reconcileGift($payment, $details);
                } else {
                    DB::transaction(function () use ($payment): void {
                        $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                        if ($locked->status !== 'completed') {
                            $locked->update(['status' => 'cancelled']);
                            $locked->order()->update(['status' => 'cancelled']);
                        }
                    }, 5);
                }
                $this->record($payment, 'cancelled');

                return $payment->fresh()->status === 'completed' ? 'completed' : 'cancelled';
            }

            $result = $payment->operation === 'wallet_deposit'
                ? $this->deposits->reconcile($payment, $details)
                : $this->purchases->reconcile($payment, $details);
            // A plan checkout refused before capture is final; it is not retried.
            if ($payment->operation === 'plan_subscription' && in_array($result, ['cancelled', 'failed'], true)) {
                $this->record($payment, 'cancelled');

                return 'cancelled';
            }
            $this->record($payment, $result === 'completed' ? 'completed' : 'pending');
            Log::info('payment_verification_result', [
                'payment_id' => $payment->id,
                'gateway' => 'paypal',
                'provider_order_id' => $payment->paypal_order_id,
                'trigger' => $trigger,
                'attempt' => $payment->verification_attempts,
                'provider_status' => $status,
                'outcome' => $result,
            ]);

            return $result === 'completed' ? 'completed' : 'pending';
        } catch (Throwable $exception) {
            $category = $exception instanceof UnexpectedValueException ? 'evidence_mismatch'
                : ($exception instanceof PayPalConfigurationException ? 'configuration_error'
                    : ($exception instanceof IncompletePayPalEvidenceException ? 'incomplete_evidence'
                        : ($providerResponseCategory ?? $this->exceptionCategory($exception, $phase))));
            $this->record($payment, 'pending', $category);
            Log::warning('payment_verification_failed', [
                'payment_id' => $payment->id,
                'gateway' => 'paypal',
                'provider_order_id' => $payment->paypal_order_id,
                'trigger' => $trigger,
                'attempt' => $payment->verification_attempts,
                'category' => $category,
                'reason_code' => $category,
            ]);

            return 'pending';
        }
    }

    private function responseErrorCategory(array $details): string
    {
        $status = (int) ($details['http_status'] ?? 0);
        $name = strtoupper((string) ($details['name'] ?? ''));
        if (in_array($status, [401, 403], true) || str_contains($name, 'AUTH')) {
            return 'provider_authentication';
        }
        if ($status === 429 || str_contains($name, 'RATE_LIMIT')) {
            return 'provider_rate_limited';
        }
        if ($status === 408 || str_contains($name, 'TIMEOUT')) {
            return 'provider_timeout';
        }

        if ($status >= 500 || str_contains($name, 'SERVER_ERROR') || str_contains($name, 'UNAVAILABLE') || str_contains($name, 'TEMPORARY')) {
            return 'provider_unavailable';
        }
        return $status === 0 && $name === '' ? 'provider_incomplete_response' : 'provider_invalid_response';
    }

    private function exceptionCategory(Throwable $exception, string $phase): string
    {
        if ($exception instanceof ConnectionException) {
            return 'provider_network';
        }
        if ($exception instanceof RequestException) {
            return $this->responseErrorCategory(['http_status' => $exception->response?->status()]);
        }

        return $phase === 'lookup' ? 'provider_lookup_error' : 'settlement_error';
    }

    private function validateOrder(Payment $payment, array $details): string
    {
        if (($details['id'] ?? null) !== $payment->paypal_order_id) {
            throw new UnexpectedValueException('PayPal order identity or state is invalid.');
        }
        if (! is_string($details['status'] ?? null) || trim($details['status']) === '') {
            throw new IncompletePayPalEvidenceException('PayPal order status evidence is incomplete.');
        }
        if (! in_array($details['status'], ['CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED', 'APPROVED', 'COMPLETED', 'VOIDED'], true)
            || (isset($details['intent']) && $details['intent'] !== 'CAPTURE')) {
            throw new UnexpectedValueException('PayPal order state is invalid.');
        }

        $units = $details['purchase_units'] ?? null;
        if (! is_array($units) || $units === []) {
            throw new IncompletePayPalEvidenceException('PayPal purchase unit evidence is incomplete.');
        }
        if (count($units) !== 1 || ! is_array($units[0] ?? null)) {
            throw new UnexpectedValueException('PayPal purchase unit does not match.');
        }
        $unit = $units[0];
        $order = $payment->order;
        if (! $order || (int) $order->user_id !== (int) $payment->user_id
            || (int) $order->amount_minor !== (int) $payment->amount_minor
            || strtoupper((string) $order->currency) !== strtoupper((string) $payment->currency)) {
            throw new UnexpectedValueException('PayPal order metadata does not match the local payment.');
        }

        foreach (['custom_id' => $unit['custom_id'] ?? null, 'reference_id' => $unit['reference_id'] ?? null,
            'amount.value' => data_get($unit, 'amount.value'), 'amount.currency_code' => data_get($unit, 'amount.currency_code')] as $field => $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new IncompletePayPalEvidenceException('PayPal order evidence is incomplete.');
            }
        }
        if ((string) $unit['custom_id'] !== (string) $payment->custom_id
            || (string) $unit['reference_id'] !== (string) $payment->reference_id
            || strtoupper((string) data_get($unit, 'amount.currency_code')) !== strtoupper((string) $payment->currency)) {
            throw new UnexpectedValueException('PayPal order metadata does not match the local payment.');
        }

        try {
            $amountMinor = $this->money->fromDecimal(data_get($unit, 'amount.value'));
        } catch (Throwable) {
            throw new UnexpectedValueException('PayPal order amount is invalid.');
        }
        if ($amountMinor !== (int) $payment->amount_minor) {
            throw new UnexpectedValueException('PayPal order amount does not match.');
        }

        $merchant = data_get($unit, 'payee.merchant_id');
        $expectedMerchant = (string) config('paypal.merchant_id');
        if ($expectedMerchant === '') {
            throw new PayPalConfigurationException('PayPal merchant ID is not configured.');
        }
        if (! is_string($merchant) || trim($merchant) === '') {
            throw new IncompletePayPalEvidenceException('PayPal merchant evidence is incomplete.');
        }
        if (! hash_equals($expectedMerchant, $merchant)) {
            throw new UnexpectedValueException('PayPal merchant does not match.');
        }

        $captureStatus = data_get($unit, 'payments.captures.0.status');
        if ($payment->operation !== 'virtual_gift' && in_array($captureStatus, ['REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED'], true)) {
            throw new UnexpectedValueException('PayPal capture was reversed before settlement.');
        }

        if ($details['status'] === 'COMPLETED') {
            $capture = data_get($unit, 'payments.captures.0');
            if (! is_array($capture) || ! is_string($capture['id'] ?? null) || trim($capture['id']) === ''
                || ! is_string($capture['status'] ?? null) || trim($capture['status']) === ''
                || ! is_string(data_get($capture, 'amount.value'))
                || ! is_string(data_get($capture, 'amount.currency_code'))) {
                throw new IncompletePayPalEvidenceException('PayPal capture evidence is incomplete.');
            }
            $allowedCaptureStatuses = $payment->operation === 'virtual_gift'
                ? ['COMPLETED', 'REFUNDED', 'PARTIALLY_REFUNDED']
                : ['COMPLETED'];
            if (! in_array(($capture['status'] ?? null), $allowedCaptureStatuses, true)
                || strtoupper((string) data_get($capture, 'amount.currency_code')) !== strtoupper((string) $payment->currency)) {
                throw new UnexpectedValueException('PayPal capture status or currency does not match.');
            }
            if ((isset($capture['custom_id']) && (string) $capture['custom_id'] !== (string) $payment->custom_id)
                || (isset($capture['reference_id']) && (string) $capture['reference_id'] !== (string) $payment->reference_id)) {
                throw new UnexpectedValueException('PayPal capture identity does not match.');
            }
            $captureMerchant = data_get($capture, 'payee.merchant_id')
                ?? data_get($capture, 'seller_receivable_breakdown.paypal_fee.payee.merchant_id');
            if (is_string($captureMerchant) && $captureMerchant !== '' && ! hash_equals($expectedMerchant, $captureMerchant)) {
                throw new UnexpectedValueException('PayPal capture merchant does not match.');
            }
            try {
                $captureAmountMinor = $this->money->fromDecimal(data_get($capture, 'amount.value'));
            } catch (Throwable) {
                throw new UnexpectedValueException('PayPal capture amount is invalid.');
            }
            if ($captureAmountMinor !== (int) $payment->amount_minor) {
                throw new UnexpectedValueException('PayPal capture amount does not match.');
            }
        }

        return $details['status'];
    }

    private function record(Payment $payment, string $outcome, ?string $errorCategory = null): void
    {
        DB::transaction(function () use ($payment, $outcome, $errorCategory): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $completed = $locked->status === 'completed' || $outcome === 'completed';
            $retryable = in_array($errorCategory, ['incomplete_evidence', 'provider_incomplete_response', 'provider_network', 'provider_timeout', 'provider_rate_limited', 'provider_unavailable'], true);
            $review = ($errorCategory !== null && ! $retryable) || ($retryable && $locked->verification_attempts >= self::MAX_ATTEMPTS);
            $reversalReview = $locked->verification_review_required && $locked->verification_error_category === 'reversal_review';
            $delay = min(3600, 30 * (2 ** min(max(0, $locked->verification_attempts - 1), 7)));
            $locked->forceFill([
                'verification_lease_until' => null,
                'next_verification_at' => $completed || $review || $outcome === 'cancelled' ? null : now()->addSeconds($delay),
                'verification_review_required' => $completed ? $reversalReview : ($locked->verification_review_required || $review),
                'verification_error_category' => $completed ? ($reversalReview ? 'reversal_review' : null)
                    : ($errorCategory ?? ($outcome === 'cancelled' ? $locked->verification_error_category : null)),
            ])->save();
        }, 5);
    }
}

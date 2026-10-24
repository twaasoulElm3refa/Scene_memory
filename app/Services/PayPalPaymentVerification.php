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
    private const MAX_ATTEMPTS = 12;

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
            if (! $payment->paypal_order_id) {
                throw new UnexpectedValueException('Missing bound PayPal order.');
            }

            $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
            if (! isset($details['id'])) {
                $providerResponseCategory = $this->responseErrorCategory($details);
                throw new RuntimeException('PayPal order lookup returned no order identity.');
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
                : ($providerResponseCategory ?? $this->exceptionCategory($exception, $phase));
            $this->record($payment, 'pending', $category);
            Log::warning('payment_verification_failed', [
                'payment_id' => $payment->id,
                'gateway' => 'paypal',
                'provider_order_id' => $payment->paypal_order_id,
                'trigger' => $trigger,
                'attempt' => $payment->verification_attempts,
                'category' => $category,
                'exception' => $exception::class,
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

        return $status >= 500 ? 'provider_unavailable' : 'provider_invalid_response';
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
        if (($details['id'] ?? null) !== $payment->paypal_order_id
            || ! in_array(($details['status'] ?? null), ['CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED', 'APPROVED', 'COMPLETED', 'VOIDED'], true)
            || (isset($details['intent']) && $details['intent'] !== 'CAPTURE')) {
            throw new UnexpectedValueException('PayPal order identity or state is invalid.');
        }

        $units = $details['purchase_units'] ?? null;
        if (! is_array($units) || count($units) !== 1 || ! is_array($units[0] ?? null)) {
            throw new UnexpectedValueException('PayPal purchase unit is invalid.');
        }
        $unit = $units[0];
        $order = $payment->order;
        if (! $order || (int) $order->user_id !== (int) $payment->user_id
            || (int) $order->amount_minor !== (int) $payment->amount_minor
            || strtoupper((string) $order->currency) !== strtoupper((string) $payment->currency)
            || (string) ($unit['custom_id'] ?? '') !== (string) $payment->custom_id
            || (string) ($unit['reference_id'] ?? '') !== (string) $payment->reference_id
            || strtoupper((string) data_get($unit, 'amount.currency_code')) !== strtoupper((string) $payment->currency)
            || ! is_string(data_get($unit, 'amount.value'))) {
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
        if ($merchant !== null && ($expectedMerchant === '' || ! hash_equals($expectedMerchant, (string) $merchant))) {
            throw new UnexpectedValueException('PayPal merchant does not match.');
        }

        $captureStatus = data_get($unit, 'payments.captures.0.status');
        if ($payment->operation !== 'virtual_gift' && in_array($captureStatus, ['REFUNDED', 'PARTIALLY_REFUNDED', 'REVERSED'], true)) {
            throw new UnexpectedValueException('PayPal capture was reversed before settlement.');
        }

        return $details['status'];
    }

    private function record(Payment $payment, string $outcome, ?string $errorCategory = null): void
    {
        DB::transaction(function () use ($payment, $outcome, $errorCategory): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $completed = $locked->status === 'completed' || $outcome === 'completed';
            $review = $errorCategory === 'evidence_mismatch' || $locked->verification_attempts >= self::MAX_ATTEMPTS;
            $reversalReview = $locked->verification_review_required && $locked->verification_error_category === 'reversal_review';
            $delay = min(3600, 30 * (2 ** min(max(0, $locked->verification_attempts - 1), 7)));
            $locked->forceFill([
                'verification_lease_until' => null,
                'next_verification_at' => $completed || $review || $outcome === 'cancelled' ? null : now()->addSeconds($delay),
                'verification_review_required' => $completed ? $reversalReview : ($locked->verification_review_required || $review),
                'verification_error_category' => $completed ? ($reversalReview ? 'reversal_review' : null) : $errorCategory,
            ])->save();
        }, 5);
    }
}

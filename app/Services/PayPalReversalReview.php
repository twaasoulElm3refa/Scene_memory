<?php

namespace App\Services;

use App\Models\FinancialHold;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransactions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds, reversals and disputes on settled PayPal payments.
 *
 * Settled funds, entitlements and ledger history are preserved. The payment is
 * flagged for review, and the money the reversed payment put into a wallet is
 * held (kept out of withdrawals) until an administrator resolves it. Holds only
 * cover credits that came from the reversed payment itself, never unrelated
 * creator funds; anything else is left to the review.
 */
class PayPalReversalReview
{
    private const REASONS = [
        'PAYMENT.CAPTURE.REFUNDED' => 'refund',
        'PAYMENT.CAPTURE.REVERSED' => 'reversal',
        'CUSTOMER.DISPUTE.CREATED' => 'dispute',
    ];

    public function __construct(
        private readonly PayPalOrderResolver $resolver,
        private readonly MinorMoney $money,
    ) {}

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
        $type = (string) ($payload['event_type'] ?? '');

        $holds = DB::transaction(function () use ($payment, $payload, $resource, $type): array {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'completed') {
                return [];
            }
            $locked->forceFill([
                'verification_review_required' => true,
                'verification_error_category' => 'reversal_review',
                'next_verification_at' => null,
            ])->save();

            if ($type === 'CUSTOMER.DISPUTE.RESOLVED') {
                $this->resolveDispute($locked, $resource);

                return [];
            }
            $reason = self::REASONS[$type] ?? null;
            $reference = (string) (data_get($resource, 'dispute_id') ?: data_get($resource, 'id') ?: '');
            if (! $reason || $reference === '') {
                return [];
            }

            return $this->placeHolds($locked, $reason, $reference, $this->reversedAmount($locked, $resource, $reason), $payload);
        }, 5);

        Log::warning('paypal_reversal_review_required', [
            'payment_id' => $payment->id,
            'provider_order_id' => $payment->paypal_order_id,
            'event_id' => $payload['id'] ?? null,
            'event_type' => $type,
            'holds' => count($holds),
            'held_minor' => array_sum(array_map(fn (FinancialHold $hold) => $hold->amount_minor, $holds)),
        ]);

        return $holds === [] ? 'ignored' : 'ok';
    }

    /**
     * @return array<int, FinancialHold>
     */
    private function placeHolds(Payment $payment, string $reason, string $reference, int $reversedMinor, array $payload): array
    {
        $credits = $this->creditsFromPayment($payment);
        if ($credits === [] || $reversedMinor < 1) {
            return [];
        }
        $paidMinor = max(1, (int) $payment->amount_minor);

        // Wallets are always locked in ascending id order, like withdrawals do.
        Wallet::query()->whereIn('id', array_keys($credits))->orderBy('id')->lockForUpdate()->get();

        // Hold reads lock so an administrator's concurrent release is never missed.
        $placed = [];
        foreach ($credits as $walletId => $credit) {
            $existing = FinancialHold::query()->where([
                'payment_id' => $payment->id, 'wallet_id' => $walletId,
                'reason' => $reason, 'provider_reference' => $reference,
            ])->lockForUpdate()->first();
            if ($existing) {
                $placed[] = $existing;

                continue;
            }
            // Proportional share of the reversal, rounded up, never more than this
            // payment credited to the wallet across all of its reversals.
            $share = min($credit['amount_minor'], intdiv(($credit['amount_minor'] * $reversedMinor) + $paidMinor - 1, $paidMinor));
            $alreadyHeld = (int) FinancialHold::query()->where('payment_id', $payment->id)
                ->where('wallet_id', $walletId)->where('status', 'active')->lockForUpdate()->sum('amount_minor');
            $amount = min($share, max(0, $credit['amount_minor'] - $alreadyHeld));
            if ($amount < 1) {
                continue;
            }
            try {
                $placed[] = FinancialHold::create([
                    'wallet_id' => $walletId,
                    'user_id' => $credit['user_id'],
                    'payment_id' => $payment->id,
                    'reason' => $reason,
                    'provider_reference' => $reference,
                    'source_event_id' => $payload['id'] ?? null,
                    'amount_minor' => $amount,
                    'currency' => strtoupper((string) $payment->currency),
                    'status' => 'active',
                    'metadata' => [
                        'operation' => $payment->operation,
                        'event_type' => $payload['event_type'] ?? null,
                        'capture_id' => $payment->capture_id,
                        'credited_minor' => $credit['amount_minor'],
                        'reversed_minor' => $reversedMinor,
                    ],
                ]);
            } catch (UniqueConstraintViolationException) {
                $placed[] = FinancialHold::query()->where([
                    'payment_id' => $payment->id, 'wallet_id' => $walletId,
                    'reason' => $reason, 'provider_reference' => $reference,
                ])->firstOrFail();
            }
        }

        return $placed;
    }

    /**
     * Wallet credits this payment produced, keyed by wallet id.
     *
     * @return array<int, array{user_id: int, amount_minor: int}>
     */
    private function creditsFromPayment(Payment $payment): array
    {
        if ($payment->operation === 'wallet_deposit') {
            $wallet = Wallet::query()->where('user_id', $payment->user_id)->first();

            return $wallet ? [(int) $wallet->id => ['user_id' => (int) $payment->user_id, 'amount_minor' => (int) $payment->amount_minor]] : [];
        }
        if ($payment->operation !== 'purchase') {
            // Plan credits are paid out to creators per redemption; nothing from this
            // payment sits in a creator wallet, so the review decides.
            return [];
        }

        return WalletTransactions::query()
            ->where('type', 'credit')
            ->where('idempotency_key', 'like', 'seller-credit:'.$payment->id.':%')
            ->get(['wallet_id', 'user_id', 'amount_minor'])
            ->groupBy('wallet_id')
            ->map(fn ($rows) => ['user_id' => (int) $rows->first()->user_id, 'amount_minor' => (int) $rows->sum('amount_minor')])
            ->all();
    }

    private function reversedAmount(Payment $payment, array $resource, string $reason): int
    {
        $amount = $reason === 'dispute' ? data_get($resource, 'dispute_amount') : data_get($resource, 'amount');
        $paid = (int) $payment->amount_minor;
        if (! is_array($amount) || strtoupper((string) ($amount['currency_code'] ?? '')) !== strtoupper((string) $payment->currency)) {
            return $paid;
        }
        try {
            $minor = $this->money->fromDecimal((string) ($amount['value'] ?? ''));
        } catch (\InvalidArgumentException) {
            return $paid;
        }

        return max(1, min($paid, $minor));
    }

    private function resolveDispute(Payment $payment, array $resource): void
    {
        $disputeId = (string) data_get($resource, 'dispute_id', '');
        $outcome = (string) data_get($resource, 'dispute_outcome.outcome_code', '');
        if ($disputeId === '') {
            return;
        }
        $holds = FinancialHold::query()->active()->where('payment_id', $payment->id)
            ->where('reason', 'dispute')->where('provider_reference', $disputeId)->lockForUpdate()->get();
        foreach ($holds as $hold) {
            $metadata = ($hold->metadata ?? []) + ['dispute_outcome' => $outcome];
            if ($outcome === 'RESOLVED_SELLER_FAVOUR') {
                $hold->update([
                    'status' => 'released',
                    'resolved_at' => now(),
                    'resolution_note' => 'PayPal resolved the dispute in the seller\'s favour.',
                    'metadata' => $metadata,
                ]);
            } else {
                // Any other outcome stays held for an administrator to decide.
                $hold->update(['metadata' => $metadata]);
            }
        }
    }
}

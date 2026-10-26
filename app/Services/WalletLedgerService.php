<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Purchases;
use App\Models\Wallet;
use App\Models\WalletTransactions;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

class WalletLedgerService
{
    public function __construct(private readonly MinorMoney $money) {}

    public function post(
        Wallet $wallet,
        Purchases $order,
        ?Payment $payment,
        string $type,
        string $source,
        int $amountMinor,
        string $idempotencyKey,
        string $reference,
        array $metadata = [],
    ): WalletTransactions {
        if ($amountMinor < 1 || ! in_array($type, ['credit', 'debit'], true)) {
            throw new RuntimeException('Invalid wallet ledger amount or type.');
        }

        // Callers hold the wallet row lock. A plain read avoids the gap lock a locking
        // read of a missing key takes on MySQL, which deadlocks concurrent inserts;
        // the unique idempotency key still rejects a duplicate before any balance moves.
        $existing = WalletTransactions::withTrashed()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        $before = $wallet->balance_minor;
        if ($before === null) {
            $before = $this->money->fromDecimal((string) ($wallet->amount ?? '0'));
        }
        $delta = $type === 'credit' ? $amountMinor : -$amountMinor;
        if (($delta > 0 && $before > PHP_INT_MAX - $delta) || ($delta < 0 && $before < PHP_INT_MIN - $delta)) {
            throw new RuntimeException('Wallet balance overflow detected.');
        }

        $after = $before + $delta;
        if ($after < 0 && ! config('wallet.allow_negative_balance', false)) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        try {
            $transaction = $this->record($wallet, $order, $payment, $type, $source, $amountMinor, $idempotencyKey, $reference, $metadata, $before, $after);
        } catch (UniqueConstraintViolationException $exception) {
            $duplicate = WalletTransactions::withTrashed()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if (! $duplicate) {
                throw $exception;
            }

            return $duplicate;
        }

        $wallet->update([
            'balance_minor' => $after,
            'amount' => $this->money->toDecimal($after),
            'currency' => $order->currency,
        ]);

        return $transaction;
    }

    private function record(
        Wallet $wallet,
        Purchases $order,
        ?Payment $payment,
        string $type,
        string $source,
        int $amountMinor,
        string $idempotencyKey,
        string $reference,
        array $metadata,
        int $before,
        int $after,
    ): WalletTransactions {
        return WalletTransactions::create([
            'user_id' => $wallet->user_id,
            'wallet_id' => $wallet->id,
            'purchase_id' => $order->id,
            'payment_id' => $payment?->id,
            'amount' => $amountMinor,
            'amount_minor' => $amountMinor,
            'type' => $type,
            'source' => $source,
            'description' => str_replace('_', ' ', $source),
            'balance_before' => $before,
            'balance_before_minor' => $before,
            'balance_after' => $after,
            'balance_after_minor' => $after,
            'idempotency_key' => $idempotencyKey,
            'reference' => $reference,
            'slug' => $idempotencyKey,
            'metadata' => $metadata,
        ]);
    }
}

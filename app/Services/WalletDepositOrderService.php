<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\Payment;
use App\Models\Purchases;
use Illuminate\Support\Facades\DB;

class WalletDepositOrderService
{
    public function __construct(private readonly MinorMoney $money) {}

    /** @return array{order: Purchases, payment: Payment} */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $userId = (int) ($data['user_id'] ?? 0);
            $amountMinor = $this->money->fromDecimal((string) ($data['amount'] ?? '0'));
            if ($amountMinor < config('wallet.minimum_topup_minor') || $amountMinor > config('wallet.maximum_topup_minor')) {
                throw new CommerceException('Wallet top-up amount is outside the allowed range.', 422);
            }

            $key = hash('sha256', 'wallet_deposit|'.$userId.'|'.(string) $data['idempotency_key']);
            $existing = Payment::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->operation !== 'wallet_deposit') {
                    throw new CommerceException('Idempotency key belongs to another operation.', 409);
                }
                if (in_array($existing->status, ['failed', 'cancelled'], true)) {
                    throw new CommerceException('Use a new idempotency key for a failed payment.', 409);
                }

                return ['order' => $existing->order, 'payment' => $existing];
            }

            $currency = strtoupper((string) config('paypal.currency', 'USD'));
            $order = Purchases::create([
                'user_id' => $userId,
                'payment_method' => 'paypal',
                'status' => 'pending',
                'currency' => $currency,
                'amount' => $this->money->toDecimal($amountMinor),
                'amount_minor' => $amountMinor,
                'description' => $data['description'] ?? 'Wallet Deposit',
                'idempotency_key' => $key,
                'type' => 'wallet_deposit',
                'order_type' => 'wallet_deposit',
            ]);
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'operation' => 'wallet_deposit',
                'method' => 'paypal',
                'status' => 'pending',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'idempotency_key' => $key,
                'paypal_request_id' => 'create-wallet-deposit-'.$key,
            ]);

            return compact('order', 'payment');
        }, 5);
    }
}

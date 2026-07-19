<?php

namespace App\Services;

use App\Jobs\SendPaymentReceipt;
use App\Models\CartItems;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentFinalizer
{
    public function __construct(
        private readonly PaymentFinancialValidator $validator,
        private readonly WalletLedgerService $ledger,
        private readonly MinorMoney $money,
    ) {}

    public function finalizeCompletedPurchaseCapture(Payment $payment, array $resource): Purchases
    {
        return DB::transaction(function () use ($payment, $resource) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Purchases::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment->setRelation('order', $order);

            if ($payment->status === 'completed') {
                return $order;
            }
            if ($payment->operation !== 'purchase' || $payment->method !== 'paypal') {
                throw new RuntimeException('Payment operation is not a PayPal purchase.');
            }

            Log::info('purchase_finalize_started', ['payment_id' => $payment->id, 'order_id' => $order->id]);
            $validated = $this->validator->assertCompletedCapture($payment, $resource);
            $this->grantEntitlements($order, $payment);
            $this->creditSellers($order, $payment);
            $this->complete($order, $payment, $resource, $validated['captureId'], [
                'purchase_granted' => true,
            ]);
            $this->cleanPurchasedCartItems($order);
            $this->queueReceipt($payment);
            Log::info('purchase_finalize_committed', ['payment_id' => $payment->id, 'order_id' => $order->id]);

            return $order->fresh();
        }, 5);
    }

    public function finalizeCompletedWalletDepositCapture(Payment $payment, array $resource): Purchases
    {
        return DB::transaction(function () use ($payment, $resource) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Purchases::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment->setRelation('order', $order);

            if ($payment->status === 'completed' && $payment->wallet_credited) {
                return $order;
            }
            if ($payment->operation !== 'wallet_deposit' || $payment->method !== 'paypal') {
                throw new RuntimeException('Payment operation is not a wallet deposit.');
            }

            $validated = $this->validator->assertCompletedCapture($payment, $resource);
            $wallet = $this->lockedWallet($order->user_id, $order->currency);
            Log::info('wallet_credit_started', ['payment_id' => $payment->id, 'order_id' => $order->id]);
            $this->ledger->post(
                $wallet,
                $order,
                $payment,
                'credit',
                'paypal_wallet_topup',
                (int) $payment->amount_minor,
                'wallet-credit:'.$payment->id,
                $validated['captureId'],
                ['paypal_order_id' => $payment->paypal_order_id],
            );
            $this->complete($order, $payment, $resource, $validated['captureId'], [
                'wallet_credited' => true,
            ]);
            $order->update(['wallet_credited' => true]);
            $this->queueReceipt($payment);
            Log::info('wallet_credit_committed', ['payment_id' => $payment->id, 'order_id' => $order->id]);

            return $order->fresh();
        }, 5);
    }

    public function finalizeWalletPurchase(Payment $payment): Purchases
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Purchases::query()->lockForUpdate()->findOrFail($payment->order_id);
            if ($payment->status === 'completed') {
                return $order;
            }
            if ($payment->operation !== 'purchase' || $payment->method !== 'wallet') {
                throw new RuntimeException('Payment operation is not a wallet purchase.');
            }

            $wallet = $this->lockedWallet($order->user_id, $order->currency, false);
            Log::info('wallet_debit_started', ['payment_id' => $payment->id, 'order_id' => $order->id]);
            $this->ledger->post(
                $wallet,
                $order,
                $payment,
                'debit',
                'content_purchase',
                (int) $payment->amount_minor,
                'wallet-debit:'.$payment->id,
                'order:'.$order->id,
            );
            $this->grantEntitlements($order, $payment);
            $this->creditSellers($order, $payment);
            $this->complete($order, $payment, null, null, ['purchase_granted' => true]);
            $this->cleanPurchasedCartItems($order);
            $this->queueReceipt($payment);
            Log::info('wallet_debit_committed', ['payment_id' => $payment->id, 'order_id' => $order->id]);

            return $order->fresh();
        }, 5);
    }

    private function grantEntitlements(Purchases $order, Payment $payment): void
    {
        $items = $order->items()->whereNotNull('image_id')->get();
        if ($items->isEmpty()) {
            throw new RuntimeException('Cannot fulfill an order without media items.');
        }

        foreach ($items as $item) {
            $entitlement = Entitlement::firstOrCreate(
                ['user_id' => $order->user_id, 'media_id' => $item->image_id],
                [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'source' => $item->purchased_type ?: 'purchase',
                    'metadata' => ['order_type' => $order->order_type],
                    'granted_at' => now(),
                ],
            );
            if ($entitlement->wasRecentlyCreated) {
                Log::info('entitlement_granted', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'media_id' => $item->image_id,
                ]);
            }
        }
    }

    private function creditSellers(Purchases $order, Payment $payment): void
    {
        $order->loadMissing('items.image.events.user');
        foreach ($order->items as $item) {
            $seller = $item->image?->events?->user;
            if (! $seller || $seller->id === $order->user_id) {
                continue;
            }

            $wallet = $this->lockedWallet($seller->id, $order->currency);
            $amountMinor = $this->money->fromDecimal((string) $item->price);
            $this->ledger->post(
                $wallet,
                $order,
                null,
                'credit',
                'media_sale',
                $amountMinor,
                'seller-credit:'.$payment->id.':'.$item->id,
                'payment:'.$payment->id.':item:'.$item->id,
                ['payment_id' => $payment->id, 'media_id' => $item->image_id],
            );
        }
    }

    private function complete(
        Purchases $order,
        Payment $payment,
        ?array $resource,
        ?string $captureId,
        array $flags,
    ): void {
        $now = now();
        $payment->update(array_merge([
            'status' => 'completed',
            'capture_id' => $captureId,
            'merchant_id' => $resource ? app(PayPalCaptureData::class)->extractMerchantId($resource) : null,
            'gateway_response' => $resource,
            'paid_at' => $now,
            'fulfilled_at' => $now,
        ], $flags));

        $order->update([
            'status' => 'completed',
            'transaction_id' => $captureId,
            'gateway_response' => $resource,
            'paid_at' => $now,
            'fulfilled_at' => $now,
            'purchase_granted' => (bool) ($flags['purchase_granted'] ?? false),
        ]);
    }

    private function cleanPurchasedCartItems(Purchases $order): void
    {
        $ids = $order->items()->whereNotNull('source_cart_item_id')->pluck('source_cart_item_id')->unique();
        if ($ids->isEmpty()) {
            return;
        }

        $deleted = CartItems::query()
            ->whereIn('id', $ids)
            ->whereHas('cart', fn ($query) => $query->where('user_id', $order->user_id))
            ->delete();
        Log::info('cart_cleaned', ['order_id' => $order->id, 'items_deleted' => $deleted]);
        $this->clearCaches($order->user_id);
    }

    private function lockedWallet(int $userId, string $currency, bool $create = true): Wallet
    {
        $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();
        if (! $wallet && $create) {
            Wallet::firstOrCreate(
                ['user_id' => $userId],
                ['amount' => '0.00', 'balance_minor' => 0, 'currency' => $currency],
            );
            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();
        }
        if (! $wallet) {
            throw new RuntimeException('Wallet not found.');
        }

        return $wallet;
    }

    private function queueReceipt(Payment $payment): void
    {
        DB::afterCommit(function () use ($payment) {
            try {
                SendPaymentReceipt::dispatch($payment->id);
            } catch (\Throwable $exception) {
                Log::error('email_failed', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    private function clearCaches(int $userId): void
    {
        try {
            Cache::tags(['cart', 'user_'.$userId])->flush();
            Cache::tags(['user_profile', 'user_'.$userId])->flush();
        } catch (\Throwable) {
            Cache::forget('cart_user_'.$userId);
            Cache::forget('user_profile_'.$userId);
        }
    }
}

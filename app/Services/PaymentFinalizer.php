<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Jobs\SendPaymentReceipt;
use App\Models\CartItems;
use App\Models\Entitlement;
use App\Models\GiftTransaction;
use App\Models\LicenceType;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\Subscriptions;
use App\Models\User;
use App\Models\Wallet;
use App\Support\SecurityMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentFinalizer
{
    private const DIRECT_SALE_COMMISSION_PERCENT = 15;

    public function finalizeVirtualGift(Payment $payment, ?array $resource = null): GiftTransaction
    {
        return app(VirtualGiftService::class)->finalize($payment, $resource);
    }

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
            $this->lockOrderWallets($order, false);
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

    public function finalizeCompletedPlanSubscriptionCapture(Payment $payment, array $resource): Purchases
    {
        return DB::transaction(function () use ($payment, $resource) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Purchases::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment->setRelation('order', $order);

            if ($payment->status === 'completed') {
                return $order;
            }
            if ($payment->operation !== 'plan_subscription' || $payment->method !== 'paypal') {
                throw new RuntimeException('Payment operation is not a PayPal plan subscription.');
            }

            $validated = $this->validator->assertCompletedCapture($payment, $resource);

            // Extract plan_id from custom_id e.g. "plan:1"
            $planId = null;
            if (preg_match('/^plan:(\d+)$/', (string) $payment->custom_id, $matches)) {
                $planId = (int) $matches[1];
            }
            $plan = $planId ? LicenceType::findOrFail($planId) : null;
            if (! $plan) {
                throw new RuntimeException('Plan not found for this subscription payment.');
            }

            $user = User::query()->lockForUpdate()->findOrFail($order->user_id);
            app(PlanQuotaService::class)->grantCredits($user, $plan, $order, 'paypal', $validated['captureId']);
            $user->update(['licence_type_id' => $plan->id]);

            Subscriptions::updateOrCreate(
                ['user_id' => $user->id, 'licence_id' => $plan->id],
                ['status' => 'active', 'start_at' => now()->toDateString(), 'end_at' => now()->addMonth()->toDateString()]
            );

            $this->complete($order, $payment, $resource, $validated['captureId'], [
                'purchase_granted' => true,
            ]);

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

            $this->lockOrderWallets($order, true);
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
            $grossMinor = $this->money->fromDecimal((string) $item->price);
            $commissionMinor = intdiv(
                ($grossMinor * self::DIRECT_SALE_COMMISSION_PERCENT) + 50,
                100
            );
            $creatorPayoutMinor = $grossMinor - $commissionMinor;
            $this->ledger->post(
                $wallet,
                $order,
                null,
                'credit',
                'media_sale',
                $creatorPayoutMinor,
                'seller-credit:'.$payment->id.':'.$item->id,
                'payment:'.$payment->id.':item:'.$item->id,
                [
                    'payment_id' => $payment->id,
                    'media_id' => $item->image_id,
                    'gross_amount_minor' => $grossMinor,
                    'platform_commission_minor' => $commissionMinor,
                    'creator_payout_minor' => $creatorPayoutMinor,
                    'commission_rate_percent' => self::DIRECT_SALE_COMMISSION_PERCENT,
                ],
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
            'verification_lease_until' => null,
            'next_verification_at' => null,
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

    /**
     * Locks every existing wallet the order will touch, in ascending id order, before
     * any of them changes. Two buyers paying each other then queue instead of deadlocking.
     */
    private function lockOrderWallets(Purchases $order, bool $includeBuyer): void
    {
        $order->loadMissing('items.image.events');
        $userIds = $order->items->map(fn ($item) => $item->image?->events?->user_id)
            ->filter(fn ($sellerId) => $sellerId && (int) $sellerId !== (int) $order->user_id);
        if ($includeBuyer) {
            $userIds->push($order->user_id);
        }
        if ($userIds->isEmpty()) {
            return;
        }
        Wallet::query()->whereIn('user_id', $userIds->unique()->values())->orderBy('id')->lockForUpdate()->get();
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
        if (strtoupper((string) $wallet->currency) !== strtoupper($currency)) {
            throw new CommerceException(SecurityMessage::get('currencyMismatch'), 422);
        }
        if ($wallet->balance_minor !== null && $wallet->balance_minor !== $this->money->fromDecimal($wallet->amount)) {
            throw new CommerceException(SecurityMessage::get('invalidWithdrawal'), 422);
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

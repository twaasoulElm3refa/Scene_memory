<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Models\Comments;
use App\Models\Events;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\VirtualGift;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VirtualGiftService
{
    public function __construct(private readonly MinorMoney $money, private readonly GiftCommission $commission, private readonly WalletLedgerService $ledger) {}

    public function create(int $senderId, array $input, string $method): GiftTransaction
    {
        if (! in_array($method, ['wallet', 'paypal'], true) || config('virtual_gifts.holding_days') < 0) {
            throw new CommerceException('Gift payment configuration is unavailable.', 503);
        }

        return DB::transaction(function () use ($senderId, $input, $method) {
            $sender = User::query()->lockForUpdate()->findOrFail($senderId);
            if (! $sender->is_active) {
                throw new CommerceException('Account is inactive.', 403);
            }
            $key = hash('sha256', 'gift|'.$senderId.'|'.$input['idempotency_key']);
            $hash = hash('sha256', json_encode([$method, (int) $input['gift_id'], (int) $input['event_id'], (int) ($input['comment_id'] ?? 0), (int) $input['quantity']], JSON_THROW_ON_ERROR));
            if ($existing = GiftTransaction::where('idempotency_key', $key)->first()) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new CommerceException('Idempotency key has different purchase details.', 409);
                }

                return $existing;
            }
            if (! config('virtual_gifts.enabled', true)) {
                throw new CommerceException('New gift purchases are temporarily unavailable.', 503);
            }
            $event = $this->visibleEvent((int) $input['event_id']);
            $comment = isset($input['comment_id']) ? Comments::where('event_id', $event->id)->findOrFail($input['comment_id']) : null;
            $recipient = $comment ? $comment->user : $event->user;
            if (! $recipient || ! $recipient->is_active || $recipient->id === $senderId) {
                throw new CommerceException('This recipient is not eligible for support.');
            }
            $gift = VirtualGift::query()->where('is_active', true)->lockForUpdate()->findOrFail($input['gift_id']);
            if ((isset($input['expected_unit_price_minor']) && (int) $input['expected_unit_price_minor'] !== $gift->price_minor) || (isset($input['expected_currency']) && $input['expected_currency'] !== $gift->currency)) {
                throw new CommerceException('Gift price changed. Choose the gift again before paying.', 409);
            }
            if ($method === 'paypal' && ! in_array($gift->currency, config('virtual_gifts.paypal_currencies'), true)) {
                throw new CommerceException('This gift currency is not supported by PayPal.');
            }
            $quantity = (int) $input['quantity'];
            if ($quantity < 1 || $quantity > config('virtual_gifts.max_quantity') || $gift->price_minor < 1 || ! in_array($gift->currency, config('virtual_gifts.currencies'), true)) {
                throw new CommerceException('Invalid gift or quantity.');
            }
            if ($gift->price_minor > intdiv(config('virtual_gifts.max_gross_minor'), $quantity)) {
                throw new CommerceException('Gift total is too large.');
            }
            $gross = $gift->price_minor * $quantity;
            $split = $this->commission->split($gross);
            $reference = (string) Str::uuid();
            $order = Purchases::create(['user_id' => $senderId, 'type' => 'virtual_gift', 'order_type' => 'virtual_gift', 'payment_method' => $method, 'status' => 'pending', 'currency' => $gift->currency, 'amount_minor' => $gross, 'amount' => $this->money->toDecimal($gross), 'idempotency_key' => $key, 'description' => 'Scemory virtual gift '.$reference]);
            $payment = Payment::create(['order_id' => $order->id, 'user_id' => $senderId, 'operation' => 'virtual_gift', 'method' => $method, 'status' => 'pending', 'currency' => $gift->currency, 'amount_minor' => $gross, 'idempotency_key' => $key, 'paypal_request_id' => $method === 'paypal' ? 'gift-create-'.$reference : null]);
            $payment->update(['custom_id' => 'virtual_gift:'.$payment->id, 'reference_id' => (string) $payment->id]);
            $transaction = GiftTransaction::create(['reference' => $reference, 'idempotency_key' => $key, 'request_hash' => $hash, 'sender_user_id' => $senderId, 'recipient_user_id' => $recipient->id, 'event_id' => $event->id, 'comment_id' => $comment?->id, 'gift_id' => $gift->id, 'payment_id' => $payment->id, 'target_type' => $comment ? 'comment' : 'event', 'quantity' => $quantity, 'gift_snapshot' => ['name' => $gift->name, 'description' => $gift->description, 'image_url' => $gift->image_url, 'translations' => $gift->translations, 'slug' => $gift->slug, 'checkout_locale' => app()->getLocale()], 'unit_price_minor' => $gift->price_minor, 'gross_amount_minor' => $gross, 'platform_commission_minor' => $split['commission'], 'recipient_earning_minor' => $split['recipient'], 'currency' => $gift->currency, 'payment_method' => $method, 'processing_fee_status' => $method === 'wallet' ? 'not_applicable' : 'unknown']);
            $this->audit($transaction, 'created', 'created:'.$reference, ['method' => $method], $senderId);

            return $transaction;
        }, 5);
    }

    public function sendWallet(int $senderId, array $input): GiftTransaction
    {
        return DB::transaction(function () use ($senderId, $input) {
            $gift = $this->create($senderId, $input, 'wallet');

            return app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment);
        }, 5);
    }

    /** Called only by PaymentFinalizer, after server-side capture validation (or locked wallet debit). */
    public function finalize(Payment $original, ?array $resource, bool $reversedGiftEvidence = false): GiftTransaction
    {
        return DB::transaction(function () use ($original, $resource, $reversedGiftEvidence) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($original->id);
            $gift = GiftTransaction::where('payment_id', $payment->id)->lockForUpdate()->firstOrFail();
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            if ($payment->operation !== 'virtual_gift' || $order->type !== 'virtual_gift' || $order->user_id !== $gift->sender_user_id || $payment->user_id !== $gift->sender_user_id || $payment->amount_minor != $gift->gross_amount_minor || $payment->currency !== $gift->currency) {
                throw new RuntimeException('Gift payment snapshot does not match.');
            }
            if ($gift->paid_at) {
                if ($resource) {
                    $payment->setRelation('order', $order);
                    $validated = app(PaymentFinancialValidator::class)->assertCompletedCapture($payment, $resource);
                    if ($gift->paypal_capture_id !== $validated['captureId']) {
                        throw new RuntimeException('Gift capture ID changed.');
                    }
                    $this->recordFee($gift, $resource);
                }

                return $gift->fresh();
            }
            if (in_array($gift->payment_status, ['cancelled', 'failed', 'refunded', 'reversed'], true) && $payment->method === 'wallet') {
                throw new CommerceException('This gift cannot be paid.', 409);
            }
            if ($payment->method === 'paypal') {
                if (! $resource) {
                    throw new RuntimeException('Verified PayPal capture required.');
                }
                $payment->setRelation('order', $order);
                $validated = app(PaymentFinancialValidator::class)->assertCompletedCapture($payment, $resource, $reversedGiftEvidence);
                $payment->capture_id = $validated['captureId'];
                $gift->paypal_capture_id = $validated['captureId'];
                $this->recordFee($gift, $resource);
            } elseif ($payment->method === 'wallet') {
                $wallet = Wallet::where('user_id', $gift->sender_user_id)->lockForUpdate()->first();
                if (! $wallet || strtoupper((string) $wallet->currency) !== $gift->currency) {
                    throw new CommerceException('Wallet currency is not supported for this gift.');
                }
                if ($wallet->balance_minor !== null && $wallet->balance_minor !== $this->money->fromDecimal((string) $wallet->amount)) {
                    throw new CommerceException('Wallet balance requires reconciliation before gifting.');
                }
                if (($wallet->balance_minor ?? $this->money->fromDecimal((string) $wallet->amount)) < $gift->gross_amount_minor) {
                    throw new CommerceException('Insufficient wallet balance.');
                }
                $entry = $this->ledger->post($wallet, $order, $payment, 'debit', 'virtual_gift', $gift->gross_amount_minor, 'gift-debit:'.$gift->reference, $gift->reference);
                $gift->wallet_transaction_id = $entry->id;
            } else {
                throw new RuntimeException('Unsupported gift payment method.');
            }

            $recipient = User::whereKey($gift->recipient_user_id)->lockForUpdate()->first();
            if (! $recipient?->is_active) {
                if ($payment->method === 'wallet') {
                    throw new CommerceException('The recipient is no longer eligible.');
                }
                $gift->review_required = true;
            }
            $at = now('UTC');
            $holdingDays = (int) config('virtual_gifts.holding_days', 7);
            if ($holdingDays < 0) {
                throw new RuntimeException('Gift holding policy must be nonnegative.');
            }
            $releaseAt = $at->copy()->addDays($holdingDays);
            $gift->forceFill(['payment_status' => 'paid', 'delivery_status' => 'delivered', 'settlement_status' => 'pending', 'paid_at' => $at, 'delivered_at' => $at, 'release_at' => $releaseAt])->save();
            $payment->forceFill(['status' => 'completed', 'paid_at' => $at, 'fulfilled_at' => $at, 'gateway_response' => $resource])->save();
            $order->update(['status' => 'completed', 'transaction_id' => $payment->capture_id, 'paid_at' => $at, 'fulfilled_at' => $at]);
            $earning = GiftEarningEntry::create(['gift_transaction_id' => $gift->id, 'recipient_user_id' => $gift->recipient_user_id, 'currency' => $gift->currency, 'gross_minor' => $gift->gross_amount_minor, 'commission_minor' => $gift->platform_commission_minor, 'recipient_share_minor' => $gift->recipient_earning_minor, 'release_at' => $releaseAt]);
            app(GiftEarningsService::class)->move($earning, 'gift-earned:'.$gift->reference, 'earned', ['pending' => $gift->recipient_earning_minor]);
            $this->audit($gift, 'delivered', 'delivered:'.$gift->reference, ['capture_id' => $payment->capture_id]);
            DB::afterCommit(function () use ($gift): void {
                try {
                    Cache::tags(['user_profile', 'user_'.$gift->sender_user_id])->forget('user_profile_'.$gift->sender_user_id);
                } catch (\Throwable) {
                    Cache::forget('user_profile_'.$gift->sender_user_id);
                }
            });

            return $gift->fresh();
        }, 5);
    }

    public function recordFee(GiftTransaction $gift, array $resource): void
    {
        $capture = data_get($resource, 'purchase_units.0.payments.captures.0', $resource);
        $fee = data_get($capture, 'seller_receivable_breakdown.paypal_fee');
        if (! is_array($fee) || ! isset($fee['value'], $fee['currency_code'])) {
            return;
        }
        $currency = strtoupper((string) $fee['currency_code']);
        try {
            $parsed = app(GiftProviderFeeMoney::class)->parse((string) $fee['value'], $currency);
        } catch (\InvalidArgumentException) {
            $gift->update(['review_required' => true]);

            return;
        }
        $amount = $parsed['amount'];
        if ($gift->payment_processing_fee_minor === $amount && $gift->processing_fee_currency === $currency && $gift->processing_fee_status === 'confirmed') {
            return;
        }
        $gift->forceFill(['payment_processing_fee_minor' => $amount, 'processing_fee_currency' => $currency, 'processing_fee_precision' => $parsed['precision'], 'processing_fee_status' => 'confirmed'])->save();
        $this->audit($gift, 'processing_fee', 'fee:'.$gift->reference.':'.Str::uuid(), ['amount_minor' => $amount, 'currency' => $currency, 'precision' => $parsed['precision']]);
    }

    public function audit(GiftTransaction $gift, string $type, string $key, array $metadata = [], ?int $actor = null): void
    {
        DB::table('gift_transaction_events')->insertOrIgnore(['gift_transaction_id' => $gift->id, 'event_key' => $key, 'type' => $type, 'actor_user_id' => $actor, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now('UTC')]);
    }

    public function visibleEvent(int $id): Events
    {
        return Events::whereNull('deleted_at')->where('is_active', 1)->findOrFail($id);
    }
}

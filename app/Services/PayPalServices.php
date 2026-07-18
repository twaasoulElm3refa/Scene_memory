<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Mail\PaymentFailMail;
use App\Mail\PaymentSuccessMail;
use App\Models\CartItems;
use App\Models\Purchases;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PayPalServices implements PaymentInterface
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly CheckoutCartSnapshot $cartSnapshot,
        private readonly PayPalOrderResolver $orderResolver,
        private readonly PayPalAmountValidator $amountValidator,
    ) {}

    public function pay(array $data): array
    {
        $order = $this->cartSnapshot->create($data);

        return DB::transaction(function () use ($order) {
            $order = Purchases::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->paypal_order_id) {
                return [
                    'order' => $order,
                    'approval_url' => $this->getApprovalUrl($order->paypal_order_id),
                ];
            }

            $paypalOrder = $this->gateway->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => $this->callbackUrl('paypal.success'),
                    'cancel_url' => $this->callbackUrl('paypal.cancel'),
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'custom_id' => 'checkout:'.$order->id,
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => number_format((float) $order->amount, 2, '.', ''),
                    ],
                    'description' => $order->description,
                ]],
            ]);

            if (! isset($paypalOrder['id']) || ($paypalOrder['status'] ?? null) !== 'CREATED') {
                throw new RuntimeException('PayPal order creation failed.');
            }

            $approvalUrl = collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

            if (! $approvalUrl) {
                throw new RuntimeException('PayPal approval URL is missing.');
            }

            $order->update([
                'paypal_order_id' => $paypalOrder['id'],
                'gateway_response' => $paypalOrder,
            ]);

            return ['order' => $order->fresh(), 'approval_url' => $approvalUrl];
        });
    }

    public function success(string $token): array
    {
        $order = Purchases::query()
            ->where('paypal_order_id', $token)
            ->where('type', 'checkout')
            ->firstOrFail();

        return [
            'success' => true,
            'message' => 'Order status loaded.',
            'order_id' => $order->id,
            'order' => $order,
        ];
    }

    public function handleWebhook(array $payload): string
    {
        return match ($payload['event_type'] ?? null) {
            'CHECKOUT.ORDER.APPROVED' => $this->onOrderApproved($payload),
            'PAYMENT.CAPTURE.COMPLETED' => $this->onCaptureCompleted($payload),
            'PAYMENT.CAPTURE.PENDING' => $this->onCapturePending($payload),
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.DECLINED' => $this->onCaptureDeclined($payload),
            'PAYMENT.CAPTURE.REFUNDED',
            'PAYMENT.CAPTURE.REVERSED' => $this->onCaptureRefunded($payload),
            default => 'ignored',
        };
    }

    public function cancel(): array
    {
        return ['success' => false, 'message' => 'Payment was cancelled by the user.'];
    }

    private function onOrderApproved(array $payload): string
    {
        $order = $this->checkoutOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if (in_array($order->status, ['completed', 'refunded'], true) || $order->capture_requested_at) {
            return 'ok';
        }

        if (! in_array($order->status, ['pending', 'approved'], true)) {
            return 'ignored';
        }

        $order->update([
            'status' => 'approved',
            'capture_requested_at' => now(),
        ]);

        $capture = $this->gateway->capturePaymentOrder(
            $order->paypal_order_id,
            'capture-checkout-'.$order->id,
        );
        $status = $capture['status'] ?? null;

        if ($status === 'COMPLETED') {
            $this->completeOrder($order, $capture);

            return 'ok';
        }

        if ($status === 'PENDING') {
            $order->update(['gateway_response' => $capture]);

            return 'ok';
        }

        throw new RuntimeException('PayPal did not accept the capture request.');
    }

    private function onCaptureCompleted(array $payload): string
    {
        $order = $this->checkoutOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if ($order->status === 'completed') {
            return 'ok';
        }

        if ($order->status === 'refunded') {
            return 'ignored';
        }

        if (! in_array($order->status, ['pending', 'approved'], true)) {
            return 'ignored';
        }

        $this->completeOrder($order, $payload['resource'] ?? []);

        return 'ok';
    }

    private function onCapturePending(array $payload): string
    {
        $order = $this->checkoutOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if (! in_array($order->status, ['completed', 'refunded', 'failed'], true)) {
            $order->update([
                'status' => 'approved',
                'gateway_response' => $payload['resource'] ?? [],
            ]);
        }

        return 'ok';
    }

    private function onCaptureDeclined(array $payload): string
    {
        $order = $this->checkoutOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if (! in_array($order->status, ['completed', 'refunded'], true)) {
            $order->update([
                'status' => 'failed',
                'gateway_response' => $payload['resource'] ?? [],
            ]);
            $this->queueFailureMail($order);
        }

        return 'ok';
    }

    private function onCaptureRefunded(array $payload): string
    {
        $order = $this->checkoutOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if ($order->status === 'refunded') {
            return 'ok';
        }

        if ($order->status !== 'completed') {
            return 'ignored';
        }

        $this->amountValidator->assertMatches($order, $payload['resource'] ?? []);
        $this->reverseSellerCredits($order);
        $order->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'gateway_response' => $payload['resource'] ?? [],
        ]);

        return 'ok';
    }

    private function completeOrder(Purchases $order, array $resource): void
    {
        $this->amountValidator->assertMatches($order, $resource);
        $captureId = $this->amountValidator->captureId($resource);

        if (! $captureId) {
            throw new RuntimeException('PayPal capture ID is missing.');
        }

        $this->creditSellers($order);

        $order->update([
            'status' => 'completed',
            'transaction_id' => $captureId,
            'payer_email' => data_get($resource, 'payer.email_address', $order->payer_email),
            'gateway_response' => $resource,
            'paid_at' => now(),
        ]);

        $cartItemIds = $order->items()
            ->whereNotNull('source_cart_item_id')
            ->pluck('source_cart_item_id')
            ->unique();

        if ($cartItemIds->isNotEmpty()) {
            CartItems::query()->whereIn('id', $cartItemIds)->delete();
        }

        $this->clearCommerceCache($order->user_id);
        $this->queueSuccessMail($order);
    }

    private function creditSellers(Purchases $order): void
    {
        $order->loadMissing('items.image.events.user');

        foreach ($order->items as $item) {
            $seller = $item->image?->events?->user;

            if (! $seller) {
                Log::warning('Seller could not be resolved for a purchased image', [
                    'order_id' => $order->id,
                    'purchase_item_id' => $item->id,
                ]);

                continue;
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $seller->id],
                ['amount' => 0, 'currency' => $order->currency],
            );

            // TODO: Apply the configured platform commission after the business rule is defined.
            $sellerAmount = (float) $item->price;
            $wallet->increment('amount', $sellerAmount);
            $this->forgetUserProfileCache($seller->id);
        }
    }

    private function reverseSellerCredits(Purchases $order): void
    {
        $order->loadMissing('items.image.events.user');

        foreach ($order->items as $item) {
            $seller = $item->image?->events?->user;

            if (! $seller) {
                continue;
            }

            $wallet = Wallet::query()->where('user_id', $seller->id)->lockForUpdate()->first();

            if ($wallet) {
                $wallet->decrement('amount', (float) $item->price);
                $this->forgetUserProfileCache($seller->id);
            }
        }
    }

    private function checkoutOrder(array $payload): ?Purchases
    {
        $order = $this->orderResolver->resolve($payload);

        return $order && $order->type === 'checkout' ? $order : null;
    }

    private function callbackUrl(string $routeName): string
    {
        return rtrim((string) config('app.url'), '/').route($routeName, [], false);
    }

    private function getApprovalUrl(string $paypalOrderId): string
    {
        $details = $this->gateway->showOrderDetails($paypalOrderId);

        return collect($details['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? throw new RuntimeException('PayPal approval URL is missing.');
    }

    private function queueSuccessMail(Purchases $order): void
    {
        if ($order->mail_sent || ! $order->user?->email) {
            return;
        }

        Mail::to($order->user->email)->queue(new PaymentSuccessMail($order));
        $order->update(['mail_sent' => true]);
    }

    private function queueFailureMail(Purchases $order): void
    {
        if ($order->mail_sent || ! $order->user?->email) {
            return;
        }

        Mail::to($order->user->email)->queue(
            new PaymentFailMail($order->amount, $order->user->name),
        );
        $order->update(['mail_sent' => true]);
    }

    private function clearCommerceCache(int $userId): void
    {
        Cache::tags(['cart', 'user_'.$userId])->flush();
        Cache::tags('user_profile')->flush();
    }

    private function forgetUserProfileCache(int $userId): void
    {
        Cache::tags(['user_profile', 'user_'.$userId])->forget('user_profile_'.$userId);
    }
}

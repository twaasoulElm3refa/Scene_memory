<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Interfaces\PaymentInterface;
use App\Models\GiftTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalServices implements PaymentInterface
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly CheckoutCartSnapshot $orderBuilder,
        private readonly PayPalOrderResolver $resolver,
        private readonly PaymentFinalizer $finalizer,
        private readonly MinorMoney $money,
    ) {}

    public function pay(array $data): array
    {
        ['order' => $order, 'payment' => $payment] = $this->orderBuilder->create($data, 'paypal');
        if ($payment->status === 'completed') {
            throw new CommerceException('This payment is already completed.', 409);
        }
        if ($payment->paypal_order_id) {
            return ['order' => $order, 'approval_url' => $this->getApprovalUrl($payment->paypal_order_id)];
        }

        $customId = 'purchase:'.$payment->id;
        $referenceId = (string) $payment->id;
        $payment->update(['custom_id' => $customId, 'reference_id' => $referenceId]);
        $paypalOrder = $this->gateway->createOrder([
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $this->callbackUrl('paypal.success'),
                'cancel_url' => $this->callbackUrl('paypal.cancel'),
            ],
            'purchase_units' => [[
                'reference_id' => $referenceId,
                'custom_id' => $customId,
                'amount' => [
                    'currency_code' => $payment->currency,
                    'value' => $this->money->toDecimal((int) $payment->amount_minor),
                ],
                'description' => $order->description,
            ]],
        ], $payment->paypal_request_id);

        $approvalUrl = $this->assertCreatedOrder($paypalOrder);
        DB::transaction(function () use ($payment, $order, $paypalOrder) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->paypal_order_id && $locked->paypal_order_id !== $paypalOrder['id']) {
                throw new RuntimeException('Payment already belongs to another PayPal order.');
            }
            $locked->update(['paypal_order_id' => $paypalOrder['id'], 'gateway_response' => $paypalOrder]);
            $order->update(['paypal_order_id' => $paypalOrder['id'], 'gateway_response' => $paypalOrder]);
        }, 5);

        Log::info('payment_order_created', ['payment_id' => $payment->id, 'order_id' => $order->id, 'operation' => 'purchase']);

        return ['order' => $order->fresh(), 'approval_url' => $approvalUrl];
    }

    public function success(string $token): array
    {
        $payment = Payment::with('order')
            ->where('paypal_order_id', $token)
            ->where('operation', 'purchase')
            ->where('method', 'paypal')
            ->firstOrFail();

        return ['order_id' => $payment->order_id, 'order' => $payment->order];
    }

    public function handleWebhook(array $payload): string
    {
        $payment = $this->resolver->resolve($payload, false);
        if ($payment?->operation === 'virtual_gift' && $payment->method === 'paypal') {
            return $this->giftWebhook($payment, $payload);
        }
        if ($payment?->operation === 'plan_subscription' && $payment->method === 'paypal') {
            return $this->planSubscriptionWebhook($payment, $payload);
        }

        return match ($payload['event_type'] ?? null) {
            'CHECKOUT.ORDER.APPROVED' => $this->approved($payload),
            'PAYMENT.CAPTURE.COMPLETED' => $this->completed($payload),
            'PAYMENT.CAPTURE.PENDING' => $this->pending($payload),
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => $this->declined($payload),
            'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED' => $this->unhandledReversal($payload),
            default => 'ignored',
        };
    }

    public function reconcile(Payment $payment): string
    {
        if ($payment->operation === 'virtual_gift') {
            return $this->reconcileGift($payment);
        }
        if ($payment->operation === 'plan_subscription') {
            return $this->reconcilePlanSubscription($payment);
        }
        $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
        if (($details['status'] ?? null) === 'COMPLETED') {
            $this->finalizer->finalizeCompletedPurchaseCapture($payment, $details);

            return 'completed';
        }
        if (($details['status'] ?? null) === 'APPROVED') {
            $capture = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'capture-purchase-'.$payment->id);
            if (($capture['status'] ?? null) === 'COMPLETED') {
                $this->finalizer->finalizeCompletedPurchaseCapture($payment, $capture);

                return 'completed';
            }
        }

        return 'pending';
    }

    public function cancel(): array
    {
        return ['success' => false, 'message' => 'Payment was cancelled by the user.'];
    }

    public function createGiftOrder(GiftTransaction $gift): array
    {
        $payment = $gift->payment;
        if ($payment->operation !== 'virtual_gift' || $payment->method !== 'paypal') {
            throw new CommerceException('Not a PayPal gift.');
        }
        if ($gift->paid_at || in_array($payment->status, ['cancelled', 'failed'], true)) {
            return ['transaction' => $gift, 'approval_url' => null];
        }
        if ($payment->paypal_order_id) {
            return ['transaction' => $gift, 'approval_url' => $this->getApprovalUrl($payment->paypal_order_id)];
        }
        if (! config('virtual_gifts.enabled', true)) {
            throw new CommerceException('New gift purchases are temporarily unavailable.', 503);
        }
        $order = $this->gateway->createOrder([
            'intent' => 'CAPTURE',
            'application_context' => ['return_url' => $this->callbackUrl('gifts.paypal.return').'?reference='.$gift->reference, 'cancel_url' => $this->callbackUrl('gifts.paypal.return').'?reference='.$gift->reference.'&cancelled=1', 'shipping_preference' => 'NO_SHIPPING'],
            'purchase_units' => [['reference_id' => $payment->reference_id, 'custom_id' => $payment->custom_id, 'description' => 'Scemory virtual support', 'amount' => ['currency_code' => $gift->currency, 'value' => $this->money->toDecimal($gift->gross_amount_minor)]]],
        ], $payment->paypal_request_id);
        $approval = $this->assertCreatedOrder($order);
        $gift = DB::transaction(function () use ($gift, $payment, $order) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->paypal_order_id && $payment->paypal_order_id !== $order['id']) {
                throw new RuntimeException('Gift already has another PayPal order.');
            }
            $payment->update(['paypal_order_id' => $order['id']]);
            $payment->order->update(['paypal_order_id' => $order['id']]);
            $gift = GiftTransaction::lockForUpdate()->findOrFail($gift->id);
            $gift->update(['paypal_order_id' => $order['id']]);

            return $gift;
        }, 5);

        return ['transaction' => $gift, 'approval_url' => $approval];
    }

    public function reconcileGift(Payment $original): string
    {
        $payment = $original->fresh();
        if ($payment->operation !== 'virtual_gift' || $payment->method !== 'paypal' || ! $payment->paypal_order_id) {
            throw new CommerceException('No PayPal gift order exists.');
        }
        $gift = GiftTransaction::where('payment_id', $payment->id)->firstOrFail();
        $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
        $capture = data_get($details, 'purchase_units.0.payments.captures.0');
        if (is_array($capture) && in_array($capture['status'] ?? null, ['REFUNDED', 'PARTIALLY_REFUNDED'], true) && ! $gift->paid_at) {
            // Authoritative terminal capture evidence can arrive before its completion webhook.
            // Record the original paid entitlement and its reversal in one transaction.
            DB::transaction(function () use ($payment, $details, $capture) {
                $recorded = app(VirtualGiftService::class)->finalize($payment, $details, true);
                if ($capture['status'] === 'REFUNDED') {
                    app(GiftEarningsService::class)->reverse($recorded, $recorded->gross_amount_minor, 'capture-refunded:'.$recorded->paypal_capture_id);
                } else {
                    $recorded->update(['review_required' => true]);
                }
            }, 5);
        } elseif (is_array($capture) && ($capture['status'] ?? null) === 'COMPLETED') {
            $this->finalizer->finalizeVirtualGift($payment, $details);
        } elseif (! $gift->paid_at && ($details['status'] ?? null) === 'APPROVED') {
            if (in_array($gift->payment_status, ['cancelled', 'failed'], true)) {
                return $gift->payment_status;
            }
            $result = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'gift-capture-'.$gift->reference);
            if (data_get($result, 'purchase_units.0.payments.captures.0.status') === 'COMPLETED') {
                $this->finalizer->finalizeVirtualGift($payment, $result);
            } elseif (in_array(data_get($result, 'purchase_units.0.payments.captures.0.status'), ['DENIED', 'DECLINED', 'FAILED'], true)) {
                $this->giftState($gift, 'failed');
            }
        } elseif (($details['status'] ?? null) === 'VOIDED' && ! $gift->paid_at) {
            $this->giftState($gift, 'cancelled');
        }
        if ($gift->paypal_capture_id || $gift->fresh()->paypal_capture_id) {
            $gift->refresh();
            $captureDetails = $this->gateway->showCaptureDetails($gift->paypal_capture_id);
            if (($captureDetails['id'] ?? null) !== $gift->paypal_capture_id || data_get($captureDetails, 'amount.currency_code') !== $gift->currency || $this->money->fromDecimal(data_get($captureDetails, 'amount.value', '0')) !== $gift->gross_amount_minor) {
                throw new RuntimeException('Capture reconciliation does not match gift.');
            }
            DB::transaction(function () use ($gift, $captureDetails) {
                $locked = GiftTransaction::lockForUpdate()->findOrFail($gift->id);
                app(VirtualGiftService::class)->recordFee($locked, $captureDetails);
            }, 5);
            if (($captureDetails['status'] ?? null) === 'REFUNDED' && $gift->refunded_gross_minor < $gift->gross_amount_minor) {
                app(GiftEarningsService::class)->reverse($gift, $gift->gross_amount_minor - $gift->refunded_gross_minor, 'capture-refunded:'.$gift->paypal_capture_id);
            }
            if (($captureDetails['status'] ?? null) === 'PARTIALLY_REFUNDED') {
                $marker = hash('sha256', $gift->paypal_capture_id.'|'.($captureDetails['update_time'] ?? now('UTC')->toDateString()));
                DB::transaction(function () use ($gift, $marker) {
                    $locked = GiftTransaction::lockForUpdate()->findOrFail($gift->id);
                    if ($locked->partial_refund_marker !== $marker) {
                        $locked->update(['review_required' => true, 'partial_refund_marker' => $marker]);
                        app(VirtualGiftService::class)->audit($locked, 'refund_review', 'refund-review:'.$locked->reference.':'.$marker);
                    }
                }, 5);
            }
        }
        $gift->update(['last_reconciled_at' => now('UTC')]);

        return $gift->fresh()->paid_at ? 'completed' : 'pending';
    }

    public function reconcileGiftRefund(GiftTransaction $gift, string $refundId): GiftTransaction
    {
        if ($gift->payment_method !== 'paypal' || ! $gift->paid_at) {
            throw new CommerceException('A paid PayPal gift is required.');
        }
        $refund = $this->gateway->showRefundDetails($refundId);
        $capture = data_get($refund, 'supplementary_data.related_ids.capture_id');
        if (! $capture) {
            $up = collect($refund['links'] ?? [])->firstWhere('rel', 'up')['href'] ?? '';
            $capture = basename((string) parse_url($up, PHP_URL_PATH));
        }
        if (($refund['id'] ?? null) !== $refundId || ($refund['status'] ?? null) !== 'COMPLETED' || $capture !== $gift->paypal_capture_id || data_get($refund, 'amount.currency_code') !== $gift->currency) {
            throw new CommerceException('The verified refund does not belong to this gift.');
        }

        return app(GiftEarningsService::class)->reverse($gift, $this->money->fromDecimal(data_get($refund, 'amount.value', '0')), 'paypal-refund:'.$refundId);
    }

    public function refundGift(GiftTransaction $original, int $actor): GiftTransaction
    {
        $gift = DB::transaction(function () use ($original, $actor) {
            $gift = GiftTransaction::lockForUpdate()->findOrFail($original->id);
            if (! $gift->paid_at || $gift->disputed) {
                throw new CommerceException('Gift is unpaid or disputed.');
            }
            if ($gift->refunded_gross_minor === $gift->gross_amount_minor) {
                return $gift;
            }
            if (! $gift->refund_request_id) {
                $gift->update(['refund_request_id' => 'gift-refund-'.$gift->reference, 'refund_requested_minor' => $gift->gross_amount_minor - $gift->refunded_gross_minor, 'review_required' => true]);
            }
            app(VirtualGiftService::class)->audit($gift, 'refund_requested', 'requested:'.$gift->refund_request_id, [], $actor);

            return $gift;
        }, 5);
        if ($gift->refunded_gross_minor === $gift->gross_amount_minor) {
            return $gift;
        }
        if ($gift->payment_method === 'wallet') {
            return app(GiftEarningsService::class)->reverse($gift, (int) $gift->refund_requested_minor, $gift->refund_request_id);
        }
        $refund = $this->gateway->refundCapture($gift->paypal_capture_id, $this->money->toDecimal((int) $gift->refund_requested_minor), $gift->currency, $gift->refund_request_id);
        if (($refund['status'] ?? null) === 'COMPLETED') {
            if (data_get($refund, 'amount.currency_code') !== $gift->currency || $this->money->fromDecimal(data_get($refund, 'amount.value', '0')) !== (int) $gift->refund_requested_minor || empty($refund['id'])) {
                throw new RuntimeException('PayPal refund amount does not match.');
            }

            return app(GiftEarningsService::class)->reverse($gift, (int) $gift->refund_requested_minor, 'paypal-refund:'.$refund['id']);
        }
        if (($refund['status'] ?? null) !== 'PENDING') {
            throw new RuntimeException('PayPal refund was not accepted.');
        }

        return $gift;
    }

    private function planSubscriptionWebhook(Payment $payment, array $payload): string
    {
        $resource = $payload['resource'] ?? [];
        $type = $payload['event_type'] ?? '';

        if ($type === 'CHECKOUT.ORDER.APPROVED') {
            $this->reconcilePlanSubscription($payment);

            return 'ok';
        }

        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $this->finalizer->finalizeCompletedPlanSubscriptionCapture($payment, $resource);

            return 'ok';
        }

        if (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED'], true)) {
            DB::transaction(function () use ($payment) {
                $payment->update(['status' => 'failed']);
                $payment->order->update(['status' => 'failed']);
            }, 5);

            return 'ok';
        }

        if ($type === 'PAYMENT.CAPTURE.PENDING') {
            return 'ok';
        }

        return 'ignored';
    }

    public function reconcilePlanSubscription(Payment $original): string
    {
        $payment = $original->fresh();
        if ($payment->operation !== 'plan_subscription' || $payment->method !== 'paypal' || ! $payment->paypal_order_id) {
            throw new CommerceException('No PayPal plan subscription order exists.');
        }

        $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
        $capture = data_get($details, 'purchase_units.0.payments.captures.0');

        if (($details['status'] ?? null) === 'COMPLETED' || (is_array($capture) && ($capture['status'] ?? null) === 'COMPLETED')) {
            $this->finalizer->finalizeCompletedPlanSubscriptionCapture($payment, is_array($capture) ? $capture : $details);

            return 'completed';
        }

        if (($details['status'] ?? null) === 'APPROVED') {
            $capture = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'capture-plan-'.$payment->id);
            if (($capture['status'] ?? null) === 'COMPLETED') {
                $this->finalizer->finalizeCompletedPlanSubscriptionCapture($payment, $capture);

                return 'completed';
            }
            if (($capture['status'] ?? null) === 'PENDING') {
                $payment->update(['gateway_response' => $capture]);

                return 'pending';
            }
        }

        return $payment->fresh()->status === 'completed' ? 'completed' : 'pending';
    }

    private function giftWebhook(Payment $payment, array $payload): string
    {
        $gift = GiftTransaction::where('payment_id', $payment->id)->firstOrFail();
        $resource = $payload['resource'] ?? [];
        $type = $payload['event_type'] ?? '';
        if ($type === 'CHECKOUT.ORDER.APPROVED') {
            $this->reconcileGift($payment);

            return 'ok';
        }
        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $this->finalizer->finalizeVirtualGift($payment, $resource);

            return 'ok';
        }
        if (in_array($type, ['PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED'], true)) {
            $this->giftState($gift, 'failed');

            return 'ok';
        }
        if ($type === 'PAYMENT.CAPTURE.PENDING') {
            return 'ok';
        }
        if ($type === 'PAYMENT.CAPTURE.REFUNDED') {
            if (! $gift->paid_at) {
                $this->reconcileGift($payment);
            }
            $gift->refresh();
            if (data_get($resource, 'amount.currency_code') !== $gift->currency || empty($resource['id'])) {
                throw new RuntimeException('Invalid PayPal gift refund.');
            }
            $relatedCapture = data_get($resource, 'supplementary_data.related_ids.capture_id');
            if (($relatedCapture && $relatedCapture !== $gift->paypal_capture_id) || (isset($resource['status']) && $resource['status'] !== 'COMPLETED')) {
                throw new RuntimeException('Refund capture or status does not match.');
            }
            $amount = $this->money->fromDecimal(data_get($resource, 'amount.value', '0'));
            app(GiftEarningsService::class)->reverse($gift, $amount, 'paypal-refund:'.$resource['id']);

            return 'ok';
        }
        if ($type === 'PAYMENT.CAPTURE.REVERSED') {
            if (! $gift->paid_at) {
                $this->reconcileGift($payment);
                $gift->refresh();
            }
            $remaining = $gift->gross_amount_minor - $gift->refunded_gross_minor;
            if ($remaining > 0) {
                $amount = $remaining;
                if (isset($resource['amount']['value'])) {
                    if (data_get($resource, 'amount.currency_code') !== $gift->currency) {
                        throw new RuntimeException('Reversal currency does not match gift.');
                    }
                    $amount = min($remaining, $this->money->fromDecimal($resource['amount']['value']));
                }
                app(GiftEarningsService::class)->reverse($gift, $amount, 'paypal-reversed:'.$payment->capture_id, 'reversed');
                if ($amount < $remaining) {
                    $gift->update(['disputed' => true, 'review_required' => true]);
                }
            }

            return 'ok';
        }
        if (str_starts_with($type, 'CUSTOMER.DISPUTE.')) {
            $outcome = data_get($resource, 'dispute_outcome.outcome_code');
            // Dispute outcomes alone do not establish the exact refunded amount.
            // Freeze for review; financial capture/refund evidence drives reversal.
            DB::transaction(function () use ($gift, $payload, $outcome) {
                $locked = GiftTransaction::lockForUpdate()->findOrFail($gift->id);
                $locked->update(['disputed' => $outcome !== 'RESOLVED_SELLER_FAVOUR', 'review_required' => true]);
                app(VirtualGiftService::class)->audit($locked, 'dispute', 'dispute-event:'.$payload['id'], ['outcome' => $outcome]);
            }, 5);

            return 'ok';
        }

        return 'ignored';
    }

    public function giftState(GiftTransaction $original, string $state): void
    {
        DB::transaction(function () use ($original, $state) {
            $gift = GiftTransaction::lockForUpdate()->findOrFail($original->id);
            if ($gift->paid_at) {
                return;
            }
            $gift->update(['payment_status' => $state]);
            $gift->payment->update(['status' => $state]);
            $gift->payment->order->update(['status' => $state]);
            app(VirtualGiftService::class)->audit($gift, $state, $state.':'.$gift->reference);
        }, 5);
    }

    private function approved(array $payload): string
    {
        $payment = $this->purchasePayment($payload);
        if (! $payment || $payment->status === 'completed') {
            return $payment ? 'ok' : 'ignored';
        }

        $shouldCapture = DB::transaction(function () use ($payment) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->capture_requested_at || ! in_array($locked->status, ['pending', 'approved'], true)) {
                return false;
            }
            $locked->update(['status' => 'approved', 'capture_requested_at' => now()]);
            $locked->order()->update(['status' => 'approved', 'capture_requested_at' => now()]);

            return true;
        }, 5);
        if (! $shouldCapture) {
            return 'ok';
        }

        Log::info('payment_approved', ['payment_id' => $payment->id, 'order_id' => $payment->order_id]);
        $capture = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'capture-purchase-'.$payment->id);
        if (($capture['status'] ?? null) === 'COMPLETED') {
            $this->finalizer->finalizeCompletedPurchaseCapture($payment, $capture);

            return 'ok';
        }
        if (($capture['status'] ?? null) === 'PENDING') {
            $payment->update(['gateway_response' => $capture]);

            return 'ok';
        }

        throw new RuntimeException('PayPal did not accept the capture request.');
    }

    private function completed(array $payload): string
    {
        $payment = $this->purchasePayment($payload);
        if (! $payment) {
            return 'ignored';
        }
        $this->finalizer->finalizeCompletedPurchaseCapture($payment, $payload['resource'] ?? []);

        return 'ok';
    }

    private function pending(array $payload): string
    {
        $payment = $this->purchasePayment($payload);
        if (! $payment || in_array($payment->status, ['completed', 'failed'], true)) {
            return $payment ? 'ok' : 'ignored';
        }
        $payment->update(['status' => 'approved', 'gateway_response' => $payload['resource'] ?? []]);
        $payment->order()->update(['status' => 'approved']);

        return 'ok';
    }

    private function declined(array $payload): string
    {
        $payment = $this->purchasePayment($payload);
        if (! $payment) {
            return 'ignored';
        }
        if ($payment->status !== 'completed') {
            $payment->update(['status' => 'failed', 'gateway_response' => $payload['resource'] ?? []]);
            $payment->order()->update(['status' => 'failed']);
        }

        return 'ok';
    }

    private function unhandledReversal(array $payload): string
    {
        Log::warning('PayPal reversal requires manual financial policy', ['event_id' => $payload['id'] ?? null]);

        return 'ignored';
    }

    private function purchasePayment(array $payload): ?Payment
    {
        $payment = $this->resolver->resolve($payload, false);

        return $payment && $payment->operation === 'purchase' && $payment->method === 'paypal' ? $payment : null;
    }

    private function assertCreatedOrder(array $paypalOrder): string
    {
        if (! isset($paypalOrder['id']) || ($paypalOrder['status'] ?? null) !== 'CREATED') {
            throw new RuntimeException('PayPal order creation failed.');
        }

        return collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? throw new RuntimeException('PayPal approval URL is missing.');
    }

    private function callbackUrl(string $routeName): string
    {
        return rtrim((string) config('app.url'), '/').route($routeName, [], false);
    }

    private function getApprovalUrl(string $paypalOrderId): string
    {
        $details = $this->gateway->showOrderDetails($paypalOrderId);

        return collect($details['links'] ?? [])->firstWhere('rel', 'approve')['href']
            ?? throw new CommerceException('PayPal approval link is no longer available.', 409);
    }
}

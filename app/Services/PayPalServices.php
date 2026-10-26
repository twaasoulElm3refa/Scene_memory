<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Interfaces\PaymentInterface;
use App\Models\GiftTransaction;
use App\Models\LicenceType;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalServices implements PaymentInterface
{
    /** A pending plan checkout younger than this is reused instead of opening a second PayPal order. */
    private const PLAN_ORDER_REUSE_MINUTES = 30;

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

    /**
     * Opens the PayPal order for a plan purchase. A pending checkout for the same
     * user, plan and price from the last few minutes is reused, so retries and
     * double submissions never open a second PayPal order.
     *
     * @return array{order: Purchases, approval_url: ?string, state: 'created'|'reused'|'in_progress'}
     */
    public function createPlanOrder(User $user, LicenceType $plan): array
    {
        $priceMinor = $this->money->fromDecimal((string) $plan->price);
        $payment = DB::transaction(function () use ($user, $plan, $priceMinor) {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $open = Payment::query()
                ->where('user_id', $user->id)
                ->where('operation', 'plan_subscription')
                ->where('method', 'paypal')
                ->where('custom_id', 'plan:'.$plan->id)
                ->where('amount_minor', $priceMinor)
                ->where('status', 'pending')
                ->whereNull('capture_requested_at')
                ->where('created_at', '>=', now()->subMinutes(self::PLAN_ORDER_REUSE_MINUTES))
                ->latest('id')
                ->first();
            if ($open) {
                return $open;
            }

            $idempotencyKey = 'plan-paypal:'.hash('sha256', $user->id.':'.$plan->id.':'.Str::uuid());
            $order = Purchases::create([
                'user_id' => $user->id,
                'payment_method' => 'paypal',
                'order_type' => 'plan_subscription',
                'status' => 'pending',
                'currency' => 'USD',
                'amount' => $plan->price,
                'amount_minor' => $priceMinor,
                'idempotency_key' => $idempotencyKey,
                'description' => "PayPal subscription to plan: {$plan->name}",
            ]);

            return Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'operation' => 'plan_subscription',
                'method' => 'paypal',
                'status' => 'pending',
                'amount_minor' => $priceMinor,
                'currency' => 'USD',
                'idempotency_key' => $idempotencyKey,
                'paypal_request_id' => $idempotencyKey,
                'custom_id' => 'plan:'.$plan->id,
                'reference_id' => (string) $order->id,
            ]);
        }, 5);
        $order = $payment->order;

        if ($payment->paypal_order_id) {
            $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
            $status = $details['status'] ?? null;
            $approvalUrl = collect($details['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
            if (in_array($status, ['CREATED', 'PAYER_ACTION_REQUIRED'], true) && is_string($approvalUrl)) {
                return ['order' => $order, 'approval_url' => $approvalUrl, 'state' => 'reused'];
            }
            if (in_array($status, ['APPROVED', 'COMPLETED'], true)) {
                return ['order' => $order, 'approval_url' => null, 'state' => 'in_progress'];
            }
            if ($status !== 'VOIDED' && data_get($details, 'error.name') !== 'RESOURCE_NOT_FOUND') {
                // Unknown provider state: never risk a second order for a checkout that may still be paid.
                throw new RuntimeException('PayPal order state for the open plan checkout is unknown.');
            }
            $this->cancelPlanCheckout($payment, 'provider_order_expired');

            return $this->createPlanOrder($user, $plan);
        }

        // Retrying with the stored PayPal-Request-Id returns the same PayPal order.
        $paypalOrder = $this->gateway->createOrder([
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $this->callbackUrl('paypal.success'),
                'cancel_url' => $this->callbackUrl('paypal.cancel'),
            ],
            'purchase_units' => [[
                'reference_id' => (string) $payment->reference_id,
                'custom_id' => (string) $payment->custom_id,
                'amount' => [
                    'currency_code' => $payment->currency,
                    'value' => $this->money->toDecimal((int) $payment->amount_minor),
                ],
                'description' => $order->description,
            ]],
        ], $payment->paypal_request_id);

        if (! isset($paypalOrder['id'])) {
            // Provider error identity only; the SDK response never carries credentials here.
            Log::warning('plan_paypal_order_rejected', [
                'payment_id' => $payment->id,
                'provider_error' => is_array($paypalOrder['error'] ?? null) ? ($paypalOrder['error']['name'] ?? null) : 'unreadable_response',
                'debug_id' => data_get($paypalOrder, 'error.debug_id'),
                'paypal_mode' => config('paypal.mode') === 'live' ? 'live' : 'sandbox',
            ]);
        }
        $approvalUrl = $this->assertCreatedOrder($paypalOrder);
        DB::transaction(function () use ($payment, $order, $paypalOrder) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->paypal_order_id && $locked->paypal_order_id !== $paypalOrder['id']) {
                throw new RuntimeException('Payment already belongs to another PayPal order.');
            }
            $locked->update(['paypal_order_id' => $paypalOrder['id'], 'gateway_response' => $paypalOrder]);
            $order->update(['paypal_order_id' => $paypalOrder['id'], 'gateway_response' => $paypalOrder]);
        }, 5);

        Log::info('payment_order_created', ['payment_id' => $payment->id, 'order_id' => $order->id, 'operation' => 'plan_subscription']);

        return ['order' => $order->fresh(), 'approval_url' => $approvalUrl, 'state' => 'created'];
    }

    public function success(string $token): array
    {
        $payment = Payment::with('order')
            ->where('paypal_order_id', $token)
            ->whereIn('operation', ['purchase', 'plan_subscription'])
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
            'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED', 'CUSTOMER.DISPUTE.CREATED', 'CUSTOMER.DISPUTE.RESOLVED' => $this->unhandledReversal($payload),
            default => 'ignored',
        };
    }

    public function reconcile(Payment $payment, ?array $verifiedOrder = null): string
    {
        if ($payment->operation === 'virtual_gift') {
            return $this->reconcileGift($payment, $verifiedOrder);
        }
        if ($payment->operation === 'plan_subscription') {
            return $this->reconcilePlanSubscription($payment, $verifiedOrder);
        }
        $details = $verifiedOrder ?? $this->gateway->showOrderDetails($payment->paypal_order_id);
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

    public function reconcileGift(Payment $original, ?array $verifiedOrder = null): string
    {
        $payment = $original->fresh();
        if ($payment->operation !== 'virtual_gift' || $payment->method !== 'paypal' || ! $payment->paypal_order_id) {
            throw new CommerceException('No PayPal gift order exists.');
        }
        $gift = GiftTransaction::where('payment_id', $payment->id)->firstOrFail();
        $details = $verifiedOrder ?? $this->gateway->showOrderDetails($payment->paypal_order_id);
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
            DB::transaction(function () use ($payment, $resource) {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                // A late or replayed denial must never undo a verified, granted capture.
                if ($locked->status === 'completed') {
                    return;
                }
                $locked->update(['status' => 'failed', 'gateway_response' => $resource]);
                $locked->order()->update(['status' => 'failed']);
            }, 5);

            return 'ok';
        }

        if ($type === 'PAYMENT.CAPTURE.PENDING') {
            // Pending funds are not paid: keep the checkout pending and grant nothing.
            return 'ok';
        }

        if (in_array($type, ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED', 'CUSTOMER.DISPUTE.CREATED', 'CUSTOMER.DISPUTE.RESOLVED'], true)) {
            return $this->unhandledReversal($payload);
        }

        return 'ignored';
    }

    public function reconcilePlanSubscription(Payment $original, ?array $verifiedOrder = null): string
    {
        $payment = $original->fresh();
        if ($payment->operation !== 'plan_subscription' || $payment->method !== 'paypal' || ! $payment->paypal_order_id) {
            throw new CommerceException('No PayPal plan subscription order exists.');
        }

        $details = $verifiedOrder ?? $this->gateway->showOrderDetails($payment->paypal_order_id);
        $capture = data_get($details, 'purchase_units.0.payments.captures.0');

        if (($details['status'] ?? null) === 'COMPLETED' || (is_array($capture) && ($capture['status'] ?? null) === 'COMPLETED')) {
            $this->finalizer->finalizeCompletedPlanSubscriptionCapture($payment, $details);

            return 'completed';
        }

        if (($details['status'] ?? null) === 'APPROVED') {
            if (in_array($payment->status, ['cancelled', 'failed'], true)) {
                return $payment->status;
            }
            // Recheck the persisted plan policy before taking money, so a plan that can
            // no longer be granted is never captured.
            if ($blocker = $this->planCaptureBlocker($payment)) {
                $this->cancelPlanCheckout($payment, $blocker);

                return 'cancelled';
            }

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

    /**
     * Why the plan behind a payment cannot be granted right now, or null.
     */
    private function planCaptureBlocker(Payment $payment): ?string
    {
        $plan = preg_match('/^plan:(\d+)$/', (string) $payment->custom_id, $matches)
            ? LicenceType::find((int) $matches[1])
            : null;
        if (! $plan) {
            return 'plan_missing';
        }
        if (! app(PlanFinancialEngine::class)->analyzePlan($plan)['is_valid']) {
            return 'plan_financially_unavailable';
        }
        try {
            app(PlanQuotaService::class)->checkAccumulationLimits($payment->user()->firstOrFail(), $plan);
        } catch (CommerceException) {
            return 'accumulation_limit';
        }

        return null;
    }

    private function cancelPlanCheckout(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($locked->status, ['pending', 'approved'], true)) {
                return;
            }
            $locked->update([
                'status' => 'cancelled',
                'verification_error_category' => $reason,
                'next_verification_at' => null,
            ]);
            $locked->order()->update(['status' => 'cancelled']);
        }, 5);

        Log::warning('plan_checkout_cancelled', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'custom_id' => $payment->custom_id,
            'reason_code' => $reason,
        ]);
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
        if (! $payment) {
            return 'ignored';
        }
        $this->applyProviderState($payment, 'approved', ['completed', 'failed'], $payload['resource'] ?? []);

        return 'ok';
    }

    private function declined(array $payload): string
    {
        $payment = $this->purchasePayment($payload);
        if (! $payment) {
            return 'ignored';
        }
        $this->applyProviderState($payment, 'failed', ['completed'], $payload['resource'] ?? []);

        return 'ok';
    }

    /**
     * Applies a late or out-of-order provider state under a row lock, so a stale
     * event can never downgrade a payment that has already been settled.
     */
    public static function applyProviderState(Payment $payment, string $state, array $protected, array $resource): void
    {
        DB::transaction(function () use ($payment, $state, $protected, $resource) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (in_array($locked->status, $protected, true)) {
                return;
            }
            $locked->update(['status' => $state, 'gateway_response' => $resource]);
            $locked->order()->update(['status' => $state]);
        }, 5);
    }

    private function unhandledReversal(array $payload): string
    {
        return app(PayPalReversalReview::class)->flag($payload);
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

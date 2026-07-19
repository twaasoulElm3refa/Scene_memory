<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Interfaces\PaymentInterface;
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

<?php

namespace App\Services;

use App\Exceptions\CommerceException;
use App\Interfaces\PaymentInterface;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalWalletServices implements PaymentInterface
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly WalletDepositOrderService $orderBuilder,
        private readonly PayPalOrderResolver $resolver,
        private readonly PaymentFinalizer $finalizer,
        private readonly MinorMoney $money,
    ) {}

    public function pay(array $data): array
    {
        ['order' => $order, 'payment' => $payment] = $this->orderBuilder->create($data);
        if ($payment->status === 'completed') {
            throw new CommerceException('This wallet deposit is already completed.', 409);
        }
        if ($payment->paypal_order_id) {
            return ['order' => $order, 'approval_url' => $this->getApprovalUrl($payment->paypal_order_id)];
        }

        $customId = 'wallet_topup:'.$payment->id;
        $referenceId = (string) $payment->id;
        $payment->update(['custom_id' => $customId, 'reference_id' => $referenceId]);
        $paypalOrder = $this->gateway->createOrder([
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $this->callbackUrl('wallet.success'),
                'cancel_url' => $this->callbackUrl('wallet.cancel'),
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

        Log::info('payment_order_created', ['payment_id' => $payment->id, 'order_id' => $order->id, 'operation' => 'wallet_deposit']);
        return ['order' => $order->fresh(), 'approval_url' => $approvalUrl];
    }

    public function success(string $token): array
    {
        $payment = Payment::with('order')
            ->where('paypal_order_id', $token)
            ->where('operation', 'wallet_deposit')
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
            'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED' => 'ignored',
            default => 'ignored',
        };
    }

    public function reconcile(Payment $payment): string
    {
        $details = $this->gateway->showOrderDetails($payment->paypal_order_id);
        if (($details['status'] ?? null) === 'COMPLETED') {
            $this->finalizer->finalizeCompletedWalletDepositCapture($payment, $details);
            return 'completed';
        }
        if (($details['status'] ?? null) === 'APPROVED') {
            $capture = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'capture-wallet-deposit-'.$payment->id);
            if (($capture['status'] ?? null) === 'COMPLETED') {
                $this->finalizer->finalizeCompletedWalletDepositCapture($payment, $capture);
                return 'completed';
            }
        }
        return 'pending';
    }

    public function cancel(): array
    {
        return ['success' => false, 'message' => 'Payment cancelled by user.'];
    }

    private function approved(array $payload): string
    {
        $payment = $this->walletPayment($payload);
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
        $capture = $this->gateway->capturePaymentOrder($payment->paypal_order_id, 'capture-wallet-deposit-'.$payment->id);
        if (($capture['status'] ?? null) === 'COMPLETED') {
            $this->finalizer->finalizeCompletedWalletDepositCapture($payment, $capture);
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
        $payment = $this->walletPayment($payload);
        if (! $payment) {
            return 'ignored';
        }
        $this->finalizer->finalizeCompletedWalletDepositCapture($payment, $payload['resource'] ?? []);
        return 'ok';
    }

    private function pending(array $payload): string
    {
        $payment = $this->walletPayment($payload);
        if (! $payment || in_array($payment->status, ['completed', 'failed'], true)) {
            return $payment ? 'ok' : 'ignored';
        }
        $payment->update(['status' => 'approved', 'gateway_response' => $payload['resource'] ?? []]);
        $payment->order()->update(['status' => 'approved']);
        return 'ok';
    }

    private function declined(array $payload): string
    {
        $payment = $this->walletPayment($payload);
        if (! $payment) {
            return 'ignored';
        }
        if ($payment->status !== 'completed') {
            $payment->update(['status' => 'failed', 'gateway_response' => $payload['resource'] ?? []]);
            $payment->order()->update(['status' => 'failed']);
        }
        return 'ok';
    }

    private function walletPayment(array $payload): ?Payment
    {
        $payment = $this->resolver->resolve($payload, false);
        return $payment && $payment->operation === 'wallet_deposit' ? $payment : null;
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

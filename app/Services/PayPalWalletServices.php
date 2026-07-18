<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Mail\DepositFailMail;
use App\Mail\DepositSuccessMail;
use App\Models\Purchases;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class PayPalWalletServices implements PaymentInterface
{
    public function __construct(
        private readonly PayPalGateway $gateway,
        private readonly PayPalOrderResolver $orderResolver,
        private readonly PayPalAmountValidator $amountValidator,
    ) {}

    public function pay(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);

        if ($userId < 1) {
            throw new RuntimeException('An authenticated user is required.');
        }

        $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');

        if ((float) $amount < 1) {
            throw new RuntimeException('Deposit amount must be at least 1.00.');
        }

        $providedKey = (string) ($data['idempotency_key'] ?? $amount.'|'.now()->format('Ymd'));
        $key = hash('sha256', "wallet|{$userId}|{$providedKey}");

        return DB::transaction(function () use ($data, $userId, $amount, $key) {
            $order = Purchases::query()
                ->where('idempotency_key', $key)
                ->where('type', 'wallet_deposit')
                ->lockForUpdate()
                ->first();

            if (! $order) {
                $order = Purchases::create([
                    'idempotency_key' => $key,
                    'user_id' => $userId,
                    'payment_method' => 'paypal',
                    'amount' => $amount,
                    'currency' => config('paypal.currency', 'USD'),
                    'description' => $data['description'] ?? 'Wallet Deposit',
                    'type' => 'wallet_deposit',
                    'status' => 'pending',
                ]);
            }

            if ($order->paypal_order_id) {
                return [
                    'order' => $order,
                    'approval_url' => $this->getApprovalUrl($order->paypal_order_id),
                ];
            }

            $paypalOrder = $this->gateway->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => $this->callbackUrl('wallet.success'),
                    'cancel_url' => $this->callbackUrl('wallet.cancel'),
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'custom_id' => 'wallet_topup:'.$order->id,
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
            ->where('type', 'wallet_deposit')
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
        return ['success' => false, 'message' => 'Payment cancelled by user.'];
    }

    private function onOrderApproved(array $payload): string
    {
        $order = $this->walletOrder($payload);

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
            'capture-wallet-'.$order->id,
        );
        $status = $capture['status'] ?? null;

        if ($status === 'COMPLETED') {
            $this->completeDeposit($order, $capture);

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
        $order = $this->walletOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if ($order->wallet_credited || $order->status === 'completed') {
            return 'ok';
        }

        if ($order->status === 'refunded') {
            return 'ignored';
        }

        if (! in_array($order->status, ['pending', 'approved'], true)) {
            return 'ignored';
        }

        $this->completeDeposit($order, $payload['resource'] ?? []);

        return 'ok';
    }

    private function onCapturePending(array $payload): string
    {
        $order = $this->walletOrder($payload);

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
        $order = $this->walletOrder($payload);

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
        $order = $this->walletOrder($payload);

        if (! $order) {
            return 'ignored';
        }

        if ($order->status === 'refunded') {
            return 'ok';
        }

        if ($order->status !== 'completed' || ! $order->wallet_credited) {
            return 'ignored';
        }

        $this->amountValidator->assertMatches($order, $payload['resource'] ?? []);
        $wallet = Wallet::query()->where('user_id', $order->user_id)->lockForUpdate()->first();

        if (! $wallet) {
            throw new RuntimeException('Wallet not found while processing a refund.');
        }

        $wallet->decrement('amount', (float) $order->amount);
        $order->update([
            'status' => 'refunded',
            'wallet_credited' => false,
            'refunded_at' => now(),
            'gateway_response' => $payload['resource'] ?? [],
        ]);

        return 'ok';
    }

    private function completeDeposit(Purchases $order, array $resource): void
    {
        $this->amountValidator->assertMatches($order, $resource);
        $captureId = $this->amountValidator->captureId($resource);

        if (! $captureId) {
            throw new RuntimeException('PayPal capture ID is missing.');
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $order->user_id],
            ['amount' => 0, 'currency' => $order->currency],
        );
        $wallet->increment('amount', (float) $order->amount);

        $order->update([
            'status' => 'completed',
            'transaction_id' => $captureId,
            'wallet_credited' => true,
            'payer_email' => data_get($resource, 'payer.email_address', $order->payer_email),
            'gateway_response' => $resource,
            'paid_at' => now(),
        ]);

        $this->queueSuccessMail($order);
    }

    private function walletOrder(array $payload): ?Purchases
    {
        $order = $this->orderResolver->resolve($payload);

        return $order && $order->type === 'wallet_deposit' ? $order : null;
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

        Mail::to($order->user->email)->queue(
            new DepositSuccessMail($order->amount, $order->user->name),
        );
        $order->update(['mail_sent' => true]);
    }

    private function queueFailureMail(Purchases $order): void
    {
        if ($order->mail_sent || ! $order->user?->email) {
            return;
        }

        Mail::to($order->user->email)->queue(
            new DepositFailMail($order->amount, $order->user->name),
        );
        $order->update(['mail_sent' => true]);
    }
}

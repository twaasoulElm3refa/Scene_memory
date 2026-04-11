<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Models\purchases;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\DepositSuccessMail;
use App\Mail\DepositFailMail;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;
use Illuminate\Support\Facades\Mail;

class PayPalWalletServices implements PaymentInterface
{
    protected PayPalClient $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    public function pay(array $data): array
    {
        $key = $data['idempotency_key']
            ?? md5(($data['user_id'] ?? 'guest') . '|' . $data['amount'] . '|' . now()->format('Ymd'));

        $existing = purchases::where('idempotency_key', $key)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing && $existing->paypal_order_id) {
            $approvalUrl = $this->getApprovalUrl($existing->paypal_order_id);

            return [
                'order'        => $existing,
                'approval_url' => $approvalUrl,
            ];
        }

        return DB::transaction(function () use ($data, $key) {
            $order = purchases::create([
                'idempotency_key' => $key,
                'user_id'         => $data['user_id'] ?? null,
                'amount'          => $data['amount'],
                'currency'        => config('paypal.currency', 'USD'),
                'description'     => $data['description'] ?? 'Wallet Deposit',
                'type'            => 'wallet_deposit',
                'status'          => 'pending',
            ]);

            $paypalOrder = $this->provider->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => config('app.url') . '/api/v1/wallet/success',
                    'cancel_url' => config('app.url') . '/api/v1/wallet/cancel',
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'amount'       => [
                        'currency_code' => $order->currency,
                        'value'         => number_format($order->amount, 2, '.', ''),
                    ],
                    'description'  => $order->description,
                ]],
            ]);

            if (!isset($paypalOrder['id']) || ($paypalOrder['status'] ?? null) !== 'CREATED') {
                throw new Exception('PayPal order creation failed: ' . json_encode($paypalOrder));
            }

            $order->update([
                'paypal_order_id'  => $paypalOrder['id'],
                'gateway_response' => $paypalOrder,
            ]);

            $approvalUrl = collect($paypalOrder['links'] ?? [])
                ->firstWhere('rel', 'approve')['href']
                ?? throw new Exception('Approval URL not found.');

            return [
                'order'        => $order->fresh(),
                'approval_url' => $approvalUrl,
            ];
        });
    }

    public function success(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $order = purchases::where('paypal_order_id', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'completed') {
                return [
                    'success'  => true,
                    'message'  => 'Already completed.',
                    'order_id' => $order->id,
                    'order'    => $order,
                ];
            }

            if (!in_array($order->status, ['pending', 'approved'], true)) {
                throw new Exception("Order #{$order->id} invalid state: {$order->status}");
            }

            if ($order->status === 'pending') {
                $order->update(['status' => 'approved']);
            }

            return [
                'success'  => true,
                'message'  => 'Awaiting webhook confirmation.',
                'order_id' => $order->id,
                'order'    => $order->fresh(),
            ];
        });
    }

    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['event_type'] ?? 'unknown';
        $resource  = $payload['resource'] ?? [];

        Log::info('PayPalWalletServices: Received webhook event', ['type' => $eventType]);

        match ($eventType) {
            'CHECKOUT.ORDER.APPROVED'   => $this->onOrderApproved($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->onCaptureCompleted($resource),
            'PAYMENT.CAPTURE.DECLINED'  => $this->onCaptureDeclined($resource),
            default => Log::info('PayPalWalletServices: Unhandled event', [
                'type' => $eventType,
            ]),
        };
    }

    private function onOrderApproved(array $resource): void
    {
        $paypalOrderId = $resource['id'] ?? null;

        if (!$paypalOrderId) {
            Log::error('PayPalWalletServices: onOrderApproved - missing order id', [
                'resource' => $resource,
            ]);
            return;
        }

        Log::info('PayPalWalletServices: Capturing order', ['paypal_order_id' => $paypalOrderId]);

        $capture = $this->provider->capturePaymentOrder($paypalOrderId);

        Log::info('PayPalWalletServices: capturePaymentOrder response', [
            'paypal_order_id' => $paypalOrderId,
            'status'          => $capture['status'] ?? 'unknown',
        ]);

        if (($capture['status'] ?? '') === 'COMPLETED') {
            // استخرج الـ capture object من جوا purchase_units
            $captureData = $capture['purchase_units'][0]['payments']['captures'][0] ?? null;

            if (!$captureData) {
                Log::error('PayPalWalletServices: No capture data found in order response', [
                    'paypal_order_id' => $paypalOrderId,
                    'capture'         => $capture,
                ]);
                return;
            }

            // ضيف الـ paypal_order_id عشان onCaptureCompleted يلاقيه
            $captureData['supplementary_data']['related_ids']['order_id'] = $paypalOrderId;

            // ضيف بيانات الـ payer من الـ order
            if (isset($capture['payer'])) {
                $captureData['payer'] = $capture['payer'];
            }

            $this->onCaptureCompleted($captureData);
        } else {
            Log::warning('PayPalWalletServices: capturePaymentOrder did not complete', [
                'paypal_order_id' => $paypalOrderId,
                'response'        => $capture,
            ]);
        }
    }

    private function onCaptureCompleted(array $resource): void
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['supplementary_data']['related_ids']['paypal_order_id']
            ?? null;

        $captureId = $resource['id'] ?? null;

        Log::info('PayPalWalletServices: onCaptureCompleted called', [
            'paypal_order_id' => $paypalOrderId,
            'capture_id'      => $captureId,
        ]);

        DB::transaction(function () use ($paypalOrderId, $captureId, $resource) {

            $order = null;

            if ($paypalOrderId) {
                $order = purchases::where('paypal_order_id', $paypalOrderId)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$order && $captureId) {
                $order = purchases::where('transaction_id', $captureId)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$order) {
                Log::error('Order not found', [
                    'paypal_order_id' => $paypalOrderId,
                    'capture_id'      => $captureId,
                ]);
                return;
            }

            // 🧠 أهم شرط يمنع التكرار
            if ($order->wallet_credited) {
                Log::info("Wallet already credited for order #{$order->id}");
                return;
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $order->user_id],
                ['amount'  => 0]
            );

            $wallet->increment('amount', (float) $order->amount);

            $order->update([
                'status'           => 'completed',
                'transaction_id'   => $captureId,
                'wallet_credited'  => true,
                'payer_email'      => $resource['payer']['email_address'] ?? $order->payer_email,
                'gateway_response' => $resource,
                'paid_at'          => now(),
            ]);

            Log::info("Wallet credited successfully", [
                'order_id' => $order->id,
            ]);

            if (!$order->mail_sent) {

                Mail::to($order->user->email)->queue(
                    new DepositSuccessMail(
                        $order->amount,
                        $order->user->name
                    )
                );

                $order->update([
                    'mail_sent' => true
                ]);

                Log::info("Success email sent", [
                    'order_id' => $order->id,
                ]);
            }

        });
    }

    private function onCaptureDeclined(array $resource): void
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
            ?? $resource['id']
            ?? null;

        if (!$paypalOrderId) {
            Log::error('Declined - missing paypal order id', [
                'resource' => $resource,
            ]);
            return;
        }

        DB::transaction(function () use ($paypalOrderId, $resource) {

            $order = purchases::where('paypal_order_id', $paypalOrderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                Log::error('Declined - Order not found', [
                    'paypal_order_id' => $paypalOrderId,
                ]);
                return;
            }
            if ($order->status === 'completed') {
                Log::warning("Declined after completed for order #{$order->id}");
                return;
            }
            if ($order->status === 'failed') {
                Log::info("Order already marked as failed #{$order->id}");
                return;
            }

            $order->update([
                'status'           => 'failed',
                'gateway_response' => $resource,
            ]);

            Log::warning("Order marked as failed", [
                'order_id' => $order->id,
            ]);
            if (!$order->mail_sent) {

                Mail::to($order->user->email)->queue(
                    new DepositFailMail(
                        $order->amount,
                        $order->user->name
                    )
                );

                $order->update([
                    'mail_sent' => true
                ]);

                Log::info("Fail email sent", [
                    'order_id' => $order->id,
                ]);
            }

        });

        Log::warning("Order declined webhook processed", [
            'paypal_order_id' => $paypalOrderId,
        ]);
    }

    public function cancel(): array
    {
        return [
            'success' => false,
            'message' => 'Payment cancelled by user.',
        ];
    }

    private function getApprovalUrl(string $paypalOrderId): string
    {
        $details = $this->provider->showOrderDetails($paypalOrderId);

        return collect($details['links'] ?? [])
            ->firstWhere('rel', 'approve')['href']
            ?? throw new Exception("Cannot retrieve approval URL: {$paypalOrderId}");
    }
}

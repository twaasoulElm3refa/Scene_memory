<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Mail\PaymentFailMail;
use App\Mail\PaymentSuccessMail;
use App\Models\purchases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class PayPalServices implements PaymentInterface
{
    protected PayPalClient $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STEP 1 — Create Order (Idempotent)
    // ══════════════════════════════════════════════════════════════════════════

   public function pay(array $data): array
    {
        $key = $data['idempotency_key']
            ?? md5(($data['user_id'] ?? 'guest') . '|' . $data['amount'] . '|' . now()->format('Ymd'));

        $existing = purchases::where('idempotency_key', $key)
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', 'checkout');
            })
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing && $existing->paypal_order_id) {
            $approvalUrl = $this->getApprovalUrl($existing->paypal_order_id);
            return ['order' => $existing, 'approval_url' => $approvalUrl];
        }

        return DB::transaction(function () use ($data, $key) {
            $order = purchases::where('idempotency_key', $key)
                ->where(function ($q) {
                    $q->whereNull('type')->orWhere('type', 'checkout');
                })
                ->lockForUpdate()
                ->first();

            if (!$order) {
                $order = purchases::create([
                    'idempotency_key' => $key,
                    'user_id'         => $data['user_id'] ?? null,
                    'amount'          => $data['amount'],
                    'currency'        => config('paypal.currency', 'USD'),
                    'description'     => $data['description'] ?? 'Order Payment',
                    'type'            => 'checkout',
                    'status'          => 'pending',
                ]);
            } elseif (empty($order->type)) {
                $order->update(['type' => 'checkout']);
            }

            if ($order->paypal_order_id) {
                $approvalUrl = $this->getApprovalUrl($order->paypal_order_id);
                return ['order' => $order->fresh(), 'approval_url' => $approvalUrl];
            }

            $paypalOrder = $this->provider->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => config('app.url') . '/api/v1/paypal/success',
                    'cancel_url' => config('app.url') . '/api/v1/paypal/cancel',
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'custom_id'    => 'checkout:' . $order->id,
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
                ?? throw new Exception('Approval URL not found in PayPal response.');

            return ['order' => $order->fresh(), 'approval_url' => $approvalUrl];
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STEP 2 — User Approved (redirect back) — Optimistic only, NOT final
    // ══════════════════════════════════════════════════════════════════════════

    public function success(string $token): array
    {
        $order = purchases::where('paypal_order_id', $token)
            ->lockForUpdate()
            ->firstOrFail();

        if ($order->isCompleted()) {
            return ['success' => true, 'message' => 'Already completed.', 'order' => $order];
        }

        // ✅ لو approved أو pending → كلاهما مقبول، مش exception
        if (!$order->isPending() && $order->status !== 'approved') {
            throw new Exception("Order #{$order->id} is in invalid state: {$order->status}");
        }

        // لو لسه pending → حوّله approved
        if ($order->isPending()) {
            DB::transaction(function () use ($order) {
                $order->update(['status' => 'approved']);
            });
        }

        return [
            'success'  => true,
            'message'  => 'Payment approved. Awaiting webhook confirmation.',
            'order_id' => $order->id,
            'order'    => $order->fresh(),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STEP 3 — Webhook: Capture & Finalize (الـ source of truth)
    // ══════════════════════════════════════════════════════════════════════════

    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['event_type'] ?? 'unknown';
        $resource  = $payload['resource'] ?? [];

        match ($eventType) {
            'CHECKOUT.ORDER.APPROVED'   => $this->onOrderApproved($resource),  // ← أضف ده
            'PAYMENT.CAPTURE.COMPLETED' => $this->onCaptureCompleted($resource),
            'PAYMENT.CAPTURE.DECLINED'  => $this->onCaptureDeclined($resource),
            default => Log::info("PayPalServices: Unhandled webhook event", ['event_type' => $eventType]),
        };
    }
    // ── Private Webhook Handlers ───────────────────────────────────────────────

    private function onCaptureCompleted(array $resource): void
    {
        DB::transaction(function () use ($resource) {
            $referenceId = $resource['purchase_units'][0]['reference_id'] ?? null;

            $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
                ?? $resource['supplementary_data']['related_ids']['paypal_order_id']
                ?? (($resource['purchase_units'][0]['reference_id'] ?? null) ? ($resource['id'] ?? null) : null);

            $captureId = $resource['purchase_units'][0]['payments']['captures'][0]['id']
                ?? $resource['id']
                ?? null;

            $order = null;

            if ($referenceId) {
                $order = purchases::whereKey($referenceId)->lockForUpdate()->first();
            }

            if (!$order && $paypalOrderId) {
                $order = purchases::where('paypal_order_id', $paypalOrderId)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$order && $captureId) {
                $order = purchases::where('transaction_id', $captureId)
                    ->lockForUpdate()
                    ->first();
            }
            $items = $order->items()->first();
            $event = $items->events();
            $owner= $event->user();
            $wallet = $owner->wallet();
            $wallet->update([
                'balance' => $wallet->balance + $order->amount
            ]);
            $this->forgetUserProfileCache($owner->id);
            if (!$order) {
                Log::error('PayPalServices: Webhook - Order not found in database', [
                    'reference_id'    => $referenceId,
                    'paypal_order_id' => $paypalOrderId,
                    'capture_id'      => $captureId,
                ]);
                return;
            }

            // حماية إضافية: لو وصل event محفظة هنا تجاهله
            if ($order->type === 'wallet_deposit') {
                Log::warning('PayPalServices: Ignoring wallet_deposit inside checkout capture handler', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            // لو مكتمل بالفعل، بلاش نكرر
            if ($order->isCompleted()) {
                Log::info("PayPalServices: Order #{$order->id} already completed - skipping (idempotent)");
                return;
            }

            $capture = $resource['purchase_units'][0]['payments']['captures'][0] ?? [];

            $order->update([
                'type'             => $order->type ?: 'checkout',
                'status'           => 'completed',
                'transaction_id'   => $capture['id'] ?? $captureId,
                'payer_email'      => $resource['payer']['email_address'] ?? $order->payer_email,
                'gateway_response' => $resource,
                'paid_at'          => now(),
            ]);

            // ابعت الإيميل مرة واحدة فقط
            if (!$order->mail_sent) {
                Mail::to($order->user->email)->queue(
                    new PaymentSuccessMail($order)
                );

                $order->update([
                    'mail_sent' => true,
                ]);

                Log::info('PayPalServices: Success email queued', [
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
            Log::error('PayPalServices: Declined webhook - missing paypal order id', [
                'resource' => $resource,
            ]);
            return;
        }

        DB::transaction(function () use ($paypalOrderId, $resource) {
            $order = purchases::where('paypal_order_id', $paypalOrderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                Log::error('PayPalServices: Declined webhook - Order not found', [
                    'paypal_order_id' => $paypalOrderId,
                ]);
                return;
            }

            // حماية إضافية: لو وصل event محفظة هنا تجاهله
            if ($order->type === 'wallet_deposit') {
                Log::warning('PayPalServices: Ignoring wallet_deposit inside checkout declined handler', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            // لو كان completed بالفعل، تجاهل decline المتأخر
            if ($order->status === 'completed') {
                Log::warning("PayPalServices: Declined received after completed for order #{$order->id}");
                return;
            }

            // حدث الحالة إلى failed
            if ($order->status !== 'failed') {
                $order->update([
                    'type'             => $order->type ?: 'checkout',
                    'status'           => 'failed',
                    'gateway_response' => $resource,
                ]);

                Log::info('PayPalServices: Order status updated to failed', [
                    'order_id' => $order->id
                ]);
            }

            // ابعت الإيميل مرة واحدة فقط
            if (!$order->mail_sent) {
                Mail::to($order->user->email)->queue(
                    new PaymentFailMail(
                        $order->amount,
                        $order->user->name
                    )
                );

                $order->update([
                    'mail_sent' => true,
                ]);

                Log::info('PayPalServices: Fail email queued', [
                    'order_id' => $order->id,
                ]);
            }

            Log::warning("PayPalServices: Order #{$order->id} capture was DECLINED", [
                'paypal_order_id' => $paypalOrderId
            ]);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STEP 4 — Cancel
    // ══════════════════════════════════════════════════════════════════════════

    public function cancel(): array
    {

        return [
            'success' => false,
            'message' => 'Payment was cancelled by the user.'
        ];
    }

    private function onOrderApproved(array $resource): void
    {
        $paypalOrderId = $resource['id'] ?? null;

        Log::info('PayPalServices: Processing CHECKOUT.ORDER.APPROVED', [
            'paypal_order_id' => $paypalOrderId
        ]);

        if (!$paypalOrderId) {
            Log::error('PayPalServices: No order ID in CHECKOUT.ORDER.APPROVED');
            return;
        }

        $order = purchases::where('paypal_order_id', $paypalOrderId)->first();

        if (!$order) {
            Log::warning('PayPalServices: Order not found for approved event', [
                'paypal_order_id' => $paypalOrderId,
            ]);
            return;
        }
        if ($order->type === 'wallet_deposit') {
            Log::warning('PayPalServices: Ignoring wallet order inside checkout service', [
                'order_id'        => $order->id,
                'paypal_order_id' => $paypalOrderId,
            ]);
            return;
        }

        $capture = $this->provider->capturePaymentOrder($paypalOrderId);

        Log::info('PayPalServices: Capture response', [
            'status'   => $capture['status'] ?? null,
            'response' => $capture
        ]);

        if (($capture['status'] ?? '') === 'COMPLETED') {
            $this->onCaptureCompleted($capture);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function getApprovalUrl(string $paypalOrderId): string
    {
        Log::info('PayPalServices: Fetching approval URL for existing order', [
            'paypal_order_id' => $paypalOrderId
        ]);

        $details = $this->provider->showOrderDetails($paypalOrderId);

        $approvalUrl = collect($details['links'] ?? [])
            ->firstWhere('rel', 'approve')['href']
            ?? throw new Exception("Cannot retrieve approval URL for order: {$paypalOrderId}");

        return $approvalUrl;
    }

    private function forgetUserProfileCache(int $userId): void
    {
        Cache::tags(['user_profile', 'user_'.$userId])
            ->forget('user_profile_' . $userId);
    }
}

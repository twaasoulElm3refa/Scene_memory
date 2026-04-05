<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Models\purchases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;

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


        // ── Idempotency check ─────────────────────────────────────────────────
        $existing = purchases::where('idempotency_key', $key)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing && $existing->paypal_order_id) {
            $approvalUrl = $this->getApprovalUrl($existing->paypal_order_id);
            return ['order' => $existing, 'approval_url' => $approvalUrl];
        }

        // ── Atomic: Save pending order first ──────────────────────────────────
        return DB::transaction(function () use ($data, $key) {

            $order = purchases::firstOrCreate([
                'idempotency_key' => $key,
                'user_id'         => $data['user_id'] ?? null,
                'amount'          => $data['amount'],
                'currency'        => config('paypal.currency', 'USD'),
                'description'     => $data['description'] ?? 'Order Payment',
                'status'          => 'pending',
            ]);

            $paypalOrder = $this->provider->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => config('app.url') . '/api/v1/paypal/success',
                    'cancel_url' => config('app.url') . '/api/v1/paypal/cancel',
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value'         => number_format($order->amount, 2, '.', ''),
                    ],
                    'description' => $order->description,
                ]],
            ]);

            if (!isset($paypalOrder['id']) || $paypalOrder['status'] !== 'CREATED') {

                throw new Exception('PayPal order creation failed: ' . json_encode($paypalOrder));
            }

            // ── Persist PayPal order ID ───────────────────────────────────────
            $order->update([
                'paypal_order_id'  => $paypalOrder['id'],
                'gateway_response' => $paypalOrder,
            ]);

            $approvalUrl = collect($paypalOrder['links'])
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

        if (!$order->isPending()) {
            throw new Exception("Order #{$order->id} is in invalid state: {$order->status}");
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'approved']);
        });

        return [
            'success'  => true,
            'message'  => 'Payment approved. Awaiting webhook confirmation.',
            'order_id' => $order->id,
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

        $referenceId   = $resource['purchase_units'][0]['reference_id'] ?? null;
        $captureId     = $resource['purchase_units'][0]['payments']['captures'][0]['id']
                        ?? $resource['id']
                        ?? null;
        $paypalOrderId = $resource['id'] ?? null;


        $order = null;

        if ($referenceId) {
            $order = purchases::find($referenceId);
        }

        if (!$order && $captureId) {
            $order = purchases::where('transaction_id', $captureId)->first();
        }

        if (!$order) {
            $order = purchases::where('paypal_order_id', $paypalOrderId)->first();
        }

        if (!$order) {
            Log::error('PayPalServices: Webhook - Order not found in database', [
                'reference_id'    => $referenceId,
                'paypal_order_id' => $paypalOrderId,
                'capture_id'      => $captureId,
            ]);
            return;
        }

        if ($order->isCompleted()) {
            Log::info("PayPalServices: Order #{$order->id} already completed - skipping (idempotent)");
            return;
        }

        $capture = $resource['purchase_units'][0]['payments']['captures'][0] ?? [];

        DB::transaction(function () use ($order, $resource, $capture) {
            $order->update([
                'status'           => 'completed',
                'transaction_id'   => $capture['id'] ?? null,
                'payer_email'      => $resource['payer']['email_address'] ?? null,
                'gateway_response' => $resource,
                'paid_at'          => now(),
            ]);
        });

        // event(new PaymentCompleted($order));
    }

    private function onCaptureDeclined(array $resource): void
    {

        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id']
                      ?? $resource['id']
                      ?? null;

        $order = purchases::where('paypal_order_id', $paypalOrderId)->first();

        if (!$order) {
            Log::error('PayPalServices: Declined webhook - Order not found', [
                'paypal_order_id' => $paypalOrderId
            ]);
            return;
        }

        DB::transaction(function () use ($order, $resource) {
            $order->update([
                'status'           => 'failed',
                'gateway_response' => $resource,
            ]);

            Log::info('PayPalServices: Order status updated to failed', ['order_id' => $order->id]);
        });

        Log::warning("PayPalServices: Order #{$order->id} capture was DECLINED", [
            'paypal_order_id' => $paypalOrderId
        ]);
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

        // عمل Capture للفلوس
        $capture = $this->provider->capturePaymentOrder($paypalOrderId);

        Log::info('PayPalServices: Capture response', [
            'status'   => $capture['status'] ?? null,
            'response' => $capture
        ]);

        // لو الـ capture نجح فوراً
        if (($capture['status'] ?? '') === 'COMPLETED') {
            $captureResource = $capture;
            // نبني الـ resource بنفس شكل PAYMENT.CAPTURE.COMPLETED
            $this->onCaptureCompleted($captureResource);
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
}

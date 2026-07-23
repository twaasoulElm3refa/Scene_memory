<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Payment;
use App\Models\PaypalWebhookEvent;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalWebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paypal.webhook_id', 'checkout-webhook-test');
        config()->set('paypal.wallet_webhook_id', 'wallet-webhook-test');
        config()->set('paypal.webhooks.checkout', 'checkout-webhook-test');
        config()->set('paypal.webhooks.wallet', 'wallet-webhook-test');
        config()->set('paypal.merchant_id', 'MERCHANT-TEST');
        config()->set('paypal.currency', 'USD');
        Mail::fake();
    }

    public function test_invalid_signature_and_missing_headers_are_rejected_without_event_record(): void
    {
        $this->mockVerifier(false);
        $payload = ['id' => 'WH-INVALID', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertStatus(400);
        $this->assertDatabaseMissing('paypal_webhook_events', ['event_id' => 'WH-INVALID']);
    }

    public function test_webhook_endpoint_reached_is_logged_before_signature_rejection(): void
    {
        Log::spy();
        $this->mockVerifier(false);
        $payload = ['id' => 'WH-REACHED', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => []];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertStatus(400);

        Log::shouldHaveReceived('info')
            ->with('SCEMORY_WEBHOOK_ENDPOINT_REACHED', Mockery::on(function (array $context) {
                return $context['path'] === 'api/v1/paypal/webhook'
                    && $context['method'] === 'POST'
                    && $context['event_id'] === 'WH-REACHED'
                    && $context['event_type'] === 'PAYMENT.CAPTURE.COMPLETED'
                    && $context['has_transmission_id'] === true
                    && $context['has_transmission_sig'] === true
                    && $context['webhook_type'] === 'checkout'
                    && ! array_key_exists('authorization', array_change_key_case($context, CASE_LOWER));
            }))
            ->once();
    }

    public function test_unsupported_verified_event_is_stored_as_ignored_with_payload(): void
    {
        $this->mockVerifier(true);
        $payload = ['id' => 'WH-IGNORED', 'event_type' => 'CATALOG.PRODUCT.CREATED', 'resource' => []];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'ignored']);
        $event = PaypalWebhookEvent::where('event_id', 'WH-IGNORED')->firstOrFail();
        $this->assertSame('ignored', $event->status);
        $this->assertSame('WH-IGNORED', $event->payload['id']);
        $this->assertNotNull($event->received_at);
    }

    public function test_same_event_id_is_idempotent_per_webhook_type(): void
    {
        $this->mockVerifier(true, 2, ['checkout-webhook-test', 'wallet-webhook-test']);
        $payload = ['id' => 'WH-SHARED', 'event_type' => 'CATALOG.PRODUCT.CREATED', 'resource' => []];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['status' => 'ignored']);
        $this->postWebhook('/api/v1/wallet/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['status' => 'ignored']);

        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_id' => 'WH-SHARED',
            'webhook_type' => 'checkout',
            'status' => 'ignored',
        ]);
        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_id' => 'WH-SHARED',
            'webhook_type' => 'wallet',
            'status' => 'ignored',
        ]);
    }

    public function test_completed_purchase_capture_is_fulfilled_exactly_once(): void
    {
        $this->mockVerifier(true, 2);
        [$order, $payment, $image, $seller] = $this->purchasePayment();
        Wallet::create(['user_id' => $seller->id, 'amount' => '5.00', 'balance_minor' => 500, 'currency' => 'USD']);
        $payload = $this->capturePayload('WH-COMPLETE', $payment, 'CAPTURE-ONE');

        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertOk()->assertExactJson(['status' => 'ok']);
        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('CAPTURE-ONE', $payment->fresh()->capture_id);
        $this->assertTrue($payment->fresh()->purchase_granted);
        $this->assertDatabaseCount('entitlements', 1);
        $this->assertDatabaseHas('entitlements', ['user_id' => $order->user_id, 'media_id' => $image->id]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(1500, $seller->wallet()->first()->balance_minor);
    }

    #[DataProvider('mismatchProvider')]
    public function test_financial_mismatches_rollback_fulfillment(string $field, mixed $value): void
    {
        $this->mockVerifier(true);
        [$order, $payment, $image] = $this->purchasePayment();
        $payload = $this->capturePayload('WH-BAD-'.$field, $payment, 'CAPTURE-BAD-'.$field);
        data_set($payload, $field, $value);

        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertStatus(500);
        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertDatabaseMissing('entitlements', ['media_id' => $image->id]);
        $this->assertDatabaseHas('paypal_webhook_events', ['event_id' => 'WH-BAD-'.$field, 'status' => 'failed']);
    }

    public static function mismatchProvider(): array
    {
        return [
            'amount' => ['resource.amount.value', '1.00'],
            'currency' => ['resource.amount.currency_code', 'EUR'],
            'merchant' => ['resource.payee.merchant_id', 'WRONG'],
            'custom id' => ['resource.custom_id', 'purchase:999999'],
            'reference id' => ['resource.reference_id', '999999'],
            'capture status' => ['resource.status', 'PENDING'],
            'PayPal order' => ['resource.supplementary_data.related_ids.order_id', 'OTHER-ORDER'],
        ];
    }

    public function test_wallet_deposit_capture_credits_wallet_and_ledger_once(): void
    {
        $this->mockVerifier(true, 2, 'wallet-webhook-test');
        $user = User::factory()->create();
        $order = $this->order($user, 'wallet_deposit', 1500);
        $payment = $this->payment($order, 'wallet_deposit', 'PAYPAL-WALLET');
        Wallet::create(['user_id' => $user->id, 'amount' => '10.00', 'balance_minor' => 1000, 'currency' => 'USD']);
        $payload = $this->capturePayload('WH-WALLET', $payment, 'CAPTURE-WALLET');

        $this->postWebhook('/api/v1/wallet/webhook', $payload)->assertOk();
        $this->postWebhook('/api/v1/wallet/webhook', $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame(2500, $user->wallet()->first()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['payment_id' => $payment->id, 'source' => 'paypal_wallet_topup']);
    }

    public function test_refund_event_is_recorded_but_does_not_apply_undefined_financial_policy(): void
    {
        $this->mockVerifier(true);
        [, $payment] = $this->purchasePayment();
        $payment->update(['status' => 'completed', 'capture_id' => 'CAPTURE-REFUND']);
        $payment->order()->update(['status' => 'completed', 'transaction_id' => 'CAPTURE-REFUND']);
        $payload = [
            'id' => 'WH-REFUND', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND-1',
                'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
                'supplementary_data' => ['related_ids' => ['capture_id' => 'CAPTURE-REFUND']],
            ],
        ];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame('completed', $payment->fresh()->status);
    }

    private function purchasePayment(): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);
        $image = EventsImges::create(['event_id' => $event->id, 'is_active' => '1', 'price' => '10.00']);
        $order = $this->order($buyer, 'checkout', 1000);
        $order->items()->create(['image_id' => $image->id, 'price' => '10.00', 'purchased_type' => 'single']);
        $payment = $this->payment($order, 'purchase', 'PAYPAL-PURCHASE');
        return [$order, $payment, $image, $seller];
    }

    private function order(User $user, string $type, int $minor): Purchases
    {
        static $sequence = 0;
        $sequence++;
        return Purchases::create([
            'user_id' => $user->id, 'payment_method' => 'paypal', 'status' => 'approved', 'currency' => 'USD',
            'amount' => number_format($minor / 100, 2, '.', ''), 'amount_minor' => $minor,
            'description' => 'Test', 'idempotency_key' => 'order-'.$sequence, 'type' => $type,
            'order_type' => $type === 'wallet_deposit' ? 'wallet_deposit' : 'single_media',
        ]);
    }

    private function payment(Purchases $order, string $operation, string $paypalOrderId): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $order->user_id, 'operation' => $operation, 'method' => 'paypal',
            'status' => 'approved', 'amount_minor' => $order->amount_minor, 'currency' => 'USD',
            'idempotency_key' => 'payment-'.$order->id, 'paypal_order_id' => $paypalOrderId,
        ]);
        $payment->update([
            'custom_id' => ($operation === 'wallet_deposit' ? 'wallet_topup:' : 'purchase:').$payment->id,
            'reference_id' => (string) $payment->id,
        ]);
        $order->update(['paypal_order_id' => $paypalOrderId]);
        return $payment;
    }

    private function capturePayload(string $eventId, Payment $payment, string $captureId): array
    {
        return [
            'id' => $eventId,
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $captureId,
                'status' => 'COMPLETED',
                'amount' => ['value' => number_format($payment->amount_minor / 100, 2, '.', ''), 'currency_code' => 'USD'],
                'custom_id' => $payment->custom_id,
                'reference_id' => $payment->reference_id,
                'payee' => ['merchant_id' => 'MERCHANT-TEST'],
                'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]],
            ],
        ];
    }

    private function mockVerifier(bool $result, int $times = 1, string|array $webhookId = 'checkout-webhook-test'): void
    {
        $webhookIds = (array) $webhookId;
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->times($times)
            ->withArgs(fn ($request, $body, $id, $type) => in_array($id, $webhookIds, true)
                && in_array($type, ['checkout', 'wallet'], true))
            ->andReturn($result);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);
    }

    private function postWebhook(string $url, array $payload)
    {
        return $this->withHeaders([
            'PAYPAL-TRANSMISSION-ID' => 'transmission-test',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'test-signature',
        ])->postJson($url, $payload);
    }
}

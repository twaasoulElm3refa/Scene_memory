<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\PaypalWebhookEvent;
use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalGateway;
use App\Services\PayPalWebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paypal.webhook_id', 'checkout-webhook-test');
        config()->set('paypal.wallet_webhook_id', 'wallet-webhook-test');
        config()->set('paypal.currency', 'USD');
        Mail::fake();
    }

    public function test_checkout_webhook_is_verified_and_returns_json(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true);

        $response = $this->postWebhook('/api/v1/paypal/webhook', [
            'id' => 'WH-CHECKOUT-IGNORED',
            'event_type' => 'CATALOG.PRODUCT.CREATED',
            'resource' => [],
        ]);

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertExactJson(['status' => 'ignored']);

        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_id' => 'WH-CHECKOUT-IGNORED',
            'webhook_type' => 'checkout',
            'status' => 'processed',
        ]);
    }

    public function test_invalid_signature_is_rejected_without_recording_the_event(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', false);

        $this->postWebhook('/api/v1/paypal/webhook', [
            'id' => 'WH-INVALID',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [],
        ])->assertStatus(400)->assertExactJson(['status' => 'invalid']);

        $this->assertDatabaseMissing('paypal_webhook_events', ['event_id' => 'WH-INVALID']);
    }

    public function test_each_webhook_uses_its_own_configured_id(): void
    {
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->withArgs(fn ($request, $body, $id) => $id === 'checkout-webhook-test')
            ->andReturnTrue();
        $verifier->shouldReceive('verify')
            ->once()
            ->withArgs(fn ($request, $body, $id) => $id === 'wallet-webhook-test')
            ->andReturnTrue();
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);

        $this->postWebhook('/api/v1/paypal/webhook', [
            'id' => 'WH-ID-CHECKOUT',
            'event_type' => 'UNUSED.EVENT',
            'resource' => [],
        ])->assertOk();

        $this->postWebhook('/api/v1/wallet/webhook', [
            'id' => 'WH-ID-WALLET',
            'event_type' => 'UNUSED.EVENT',
            'resource' => [],
        ])->assertOk();
    }

    public function test_bad_bot_middleware_does_not_block_paypal_webhooks(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true);

        $this->withHeader('User-Agent', 'curl/8.0')->postJson('/api/v1/paypal/webhook', [
            'id' => 'WH-CURL',
            'event_type' => 'UNUSED.EVENT',
            'resource' => [],
        ])->assertOk()->assertExactJson(['status' => 'ignored']);
    }

    public function test_approved_order_is_captured_once_and_duplicate_event_is_ignored(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true, 2);
        $order = $this->purchase(['status' => 'pending']);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('capturePaymentOrder')
            ->once()
            ->with($order->paypal_order_id, 'capture-checkout-'.$order->id)
            ->andReturn(['status' => 'PENDING']);
        $this->app->instance(PayPalGateway::class, $gateway);

        $payload = [
            'id' => 'WH-APPROVED-ONCE',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => $order->paypal_order_id],
        ];

        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'ok']);
        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame('approved', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->capture_requested_at);
        $this->assertSame(1, PaypalWebhookEvent::query()->where('event_id', 'WH-APPROVED-ONCE')->count());
    }

    public function test_completed_capture_completes_order_and_credits_each_seller_item_once(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true, 2);
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id]);
        $image = EventsImges::create(['event_id' => $event->id, 'price' => 12.50]);
        $order = $this->purchase([
            'user_id' => $buyer->id,
            'amount' => 12.50,
            'status' => 'approved',
        ]);
        PurchaseItems::create([
            'purchase_id' => $order->id,
            'image_id' => $image->id,
            'price' => 12.50,
        ]);
        Wallet::create(['user_id' => $seller->id, 'amount' => 5, 'currency' => 'USD']);

        $payload = $this->capturePayload('WH-COMPLETED', 'PAYMENT.CAPTURE.COMPLETED', $order, 'CAPTURE-1');

        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'ok']);
        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('CAPTURE-1', $order->fresh()->transaction_id);
        $this->assertSame('17.50', number_format((float) $seller->wallet()->first()->amount, 2, '.', ''));
    }

    public function test_amount_mismatch_fails_without_completing_or_crediting_seller(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true);
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id]);
        $image = EventsImges::create(['event_id' => $event->id, 'price' => 20]);
        $order = $this->purchase(['amount' => 20, 'status' => 'approved']);
        PurchaseItems::create(['purchase_id' => $order->id, 'image_id' => $image->id, 'price' => 20]);

        $payload = $this->capturePayload('WH-MISMATCH', 'PAYMENT.CAPTURE.COMPLETED', $order, 'CAPTURE-BAD');
        $payload['resource']['amount']['value'] = '1.00';

        $this->postWebhook('/api/v1/paypal/webhook', $payload)
            ->assertStatus(500)->assertExactJson(['status' => 'error']);

        $this->assertSame('approved', $order->fresh()->status);
        $this->assertDatabaseMissing('wallets', ['user_id' => $seller->id]);
        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_id' => 'WH-MISMATCH',
            'status' => 'failed',
        ]);
    }

    public function test_denied_and_refunded_events_apply_safe_state_transitions(): void
    {
        $this->mockVerifierFor('checkout-webhook-test', true, 2);
        $denied = $this->purchase(['status' => 'approved']);

        $this->postWebhook('/api/v1/paypal/webhook', $this->capturePayload(
            'WH-DENIED',
            'PAYMENT.CAPTURE.DENIED',
            $denied,
            'CAPTURE-DENIED',
        ))->assertOk();
        $this->assertSame('failed', $denied->fresh()->status);

        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id]);
        $image = EventsImges::create(['event_id' => $event->id, 'price' => 8]);
        $refunded = $this->purchase([
            'status' => 'completed',
            'amount' => 8,
            'transaction_id' => 'CAPTURE-REFUND',
            'paid_at' => now(),
        ]);
        PurchaseItems::create(['purchase_id' => $refunded->id, 'image_id' => $image->id, 'price' => 8]);
        Wallet::create(['user_id' => $seller->id, 'amount' => 8, 'currency' => 'USD']);

        $this->postWebhook('/api/v1/paypal/webhook', [
            'id' => 'WH-REFUNDED',
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'REFUND-1',
                'amount' => ['value' => '8.00', 'currency_code' => 'USD'],
                'supplementary_data' => ['related_ids' => ['capture_id' => 'CAPTURE-REFUND']],
            ],
        ])->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertSame('refunded', $refunded->fresh()->status);
        $this->assertSame('0.00', number_format((float) $seller->wallet()->first()->amount, 2, '.', ''));
    }

    public function test_wallet_completed_event_credits_balance_only_once(): void
    {
        $this->mockVerifierFor('wallet-webhook-test', true, 2);
        $user = User::factory()->create();
        $order = $this->purchase([
            'user_id' => $user->id,
            'type' => 'wallet_deposit',
            'amount' => 15,
            'status' => 'approved',
        ]);
        Wallet::create(['user_id' => $user->id, 'amount' => 10, 'currency' => 'USD']);
        $payload = $this->capturePayload('WH-WALLET', 'PAYMENT.CAPTURE.COMPLETED', $order, 'CAPTURE-WALLET');

        $this->postWebhook('/api/v1/wallet/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'ok']);
        $this->postWebhook('/api/v1/wallet/webhook', $payload)
            ->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($order->fresh()->wallet_credited);
        $this->assertSame('25.00', number_format((float) $user->wallet()->first()->amount, 2, '.', ''));
    }

    private function mockVerifierFor(string $webhookId, bool $result, int $times = 1): void
    {
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')
            ->times($times)
            ->withArgs(fn ($request, $body, $id) => $id === $webhookId)
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

    private function purchase(array $overrides = []): Purchases
    {
        static $sequence = 0;
        $sequence++;

        return Purchases::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'paypal',
            'transaction_id' => null,
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 10,
            'paypal_order_id' => 'PAYPAL-ORDER-'.$sequence,
            'description' => 'Test order',
            'idempotency_key' => 'test-key-'.$sequence,
            'type' => 'checkout',
        ], $overrides));
    }

    private function capturePayload(
        string $eventId,
        string $eventType,
        Purchases $order,
        string $captureId,
    ): array {
        return [
            'id' => $eventId,
            'event_type' => $eventType,
            'resource' => [
                'id' => $captureId,
                'amount' => [
                    'value' => number_format((float) $order->amount, 2, '.', ''),
                    'currency_code' => $order->currency,
                ],
                'supplementary_data' => [
                    'related_ids' => ['order_id' => $order->paypal_order_id],
                ],
            ],
        ];
    }
}

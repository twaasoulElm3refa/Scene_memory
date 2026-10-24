<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalGateway;
use App\Services\PayPalPaymentVerification;
use App\Services\PayPalWebhookVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PayPalPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('paypal.merchant_id', 'MERCHANT-VERIFY');
    }

    public function test_owner_status_recovers_completed_purchase_without_webhook_and_never_fulfills_twice(): void
    {
        [$owner, $payment, $media] = $this->purchase();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->with($payment->paypal_order_id)
            ->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->app->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('entitlements', ['user_id' => $owner->id, 'media_id' => $media->id]);
        $this->assertDatabaseCount('entitlements', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(1, $payment->fresh()->verification_attempts);
    }

    public function test_reconciliation_recovers_wallet_topup_without_webhook_and_credits_once(): void
    {
        [$owner, $payment] = $this->walletDeposit();
        $payment->update(['created_at' => now()->subHour()]);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->artisan('payments:reconcile', ['--limit' => 10, '--age' => 10])->assertSuccessful();
        $this->artisan('payments:reconcile', ['--limit' => 10, '--age' => 10])->assertSuccessful();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame(1000, $owner->wallet->fresh()->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', ['payment_id' => $payment->id, 'source' => 'paypal_wallet_topup', 'amount_minor' => 1000]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_webhook_after_direct_verification_cannot_credit_wallet_twice(): void
    {
        [, $payment] = $this->walletDeposit();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->app->instance(PayPalGateway::class, $gateway);
        $this->assertSame('completed', app(PayPalPaymentVerification::class)->verify($payment));

        config()->set('paypal.webhooks.wallet', 'wallet-verification-test');
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(true);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);
        $this->withHeaders($this->webhookHeaders())->postJson('/api/v1/wallet/webhook', $this->captureEvent($payment))
            ->assertOk();

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(1000, $payment->user->wallet->fresh()->balance_minor);
    }

    public function test_webhook_first_makes_direct_verification_a_noop(): void
    {
        [, $payment, $media] = $this->purchase();
        config()->set('paypal.webhooks.checkout', 'checkout-verification-test');
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(true);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);
        $this->withHeaders($this->webhookHeaders())->postJson('/api/v1/paypal/webhook', $this->captureEvent($payment))
            ->assertOk();

        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldNotReceive('showOrderDetails');
        $this->app->instance(PayPalGateway::class, $gateway);
        $this->assertSame('completed', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertDatabaseHas('entitlements', ['media_id' => $media->id, 'payment_id' => $payment->id]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_plan_checkout_creates_consistent_provider_metadata_and_verified_capture_grants_once(): void
    {
        $owner = User::factory()->create();
        $plan = LicenceType::create(['name' => 'Verified Plan', 'slug' => 'verified-plan', 'price' => '10.00',
            'photo_quota' => 2, 'video_quota' => 1, 'max_accumulated_photos' => 10,
            'max_accumulated_videos' => 10, 'is_active' => true]);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->withArgs(function (array $request) use ($plan): bool {
            return data_get($request, 'purchase_units.0.custom_id') === 'plan:'.$plan->id
                && data_get($request, 'purchase_units.0.amount.value') === '10.00';
        })->andReturn(['id' => 'PAYPAL-PLAN', 'status' => 'CREATED', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-PLAN']]]);
        $this->app->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/v1/plans/subscribe/'.$plan->id.'/paypal')->assertOk();
        $payment = Payment::where('order_id', $created->json('order_id'))->sole();
        $this->assertSame((string) $payment->order_id, $payment->reference_id);
        $this->assertSame('plan:'.$plan->id, $payment->custom_id);

        $verifyGateway = Mockery::mock(PayPalGateway::class);
        $verifyGateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->app->instance(PayPalGateway::class, $verifyGateway);
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('user_credit_grants', ['purchase_id' => $payment->order_id, 'user_id' => $owner->id, 'photo_count' => 2, 'video_count' => 1]);
        $this->assertDatabaseCount('user_credit_grants', 1);
        $this->assertDatabaseCount('credit_transactions', 2);
    }

    public function test_approved_order_with_pending_capture_does_not_grant_entitlement(): void
    {
        [, $payment, $media] = $this->purchase();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $gateway->shouldReceive('capturePaymentOrder')->once()->andReturn(['status' => 'PENDING']);
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertDatabaseMissing('entitlements', ['media_id' => $media->id]);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->next_verification_at);
    }

    public function test_approved_order_is_captured_with_stable_request_id_before_fulfillment(): void
    {
        [, $payment, $media] = $this->purchase();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $gateway->shouldReceive('capturePaymentOrder')->once()
            ->with($payment->paypal_order_id, 'capture-purchase-'.$payment->id)
            ->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('completed', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertDatabaseHas('entitlements', ['media_id' => $media->id, 'payment_id' => $payment->id]);
    }

    #[DataProvider('invalidOrderCases')]
    public function test_provider_identity_and_financial_mismatches_require_review(string $field, mixed $value): void
    {
        [, $payment, $media] = $this->purchase();
        $details = $this->orderDetails($payment, 'COMPLETED');
        data_set($details, $field, $value);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($details);
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertTrue($payment->fresh()->verification_review_required);
        $this->assertSame('evidence_mismatch', $payment->fresh()->verification_error_category);
        $this->assertDatabaseMissing('entitlements', ['media_id' => $media->id]);
    }

    public static function invalidOrderCases(): array
    {
        return [
            'another provider order' => ['id', 'OTHER-ORDER'],
            'amount' => ['purchase_units.0.amount.value', '9.99'],
            'currency' => ['purchase_units.0.amount.currency_code', 'EUR'],
            'merchant' => ['purchase_units.0.payee.merchant_id', 'OTHER-MERCHANT'],
            'purpose' => ['purchase_units.0.custom_id', 'wallet_topup:999'],
            'reference' => ['purchase_units.0.reference_id', '999'],
            'unknown state' => ['status', 'UNKNOWN'],
        ];
    }

    public function test_completed_order_with_mismatched_capture_is_never_fulfilled(): void
    {
        [, $payment, $media] = $this->purchase();
        $details = $this->orderDetails($payment, 'COMPLETED');
        data_set($details, 'purchase_units.0.payments.captures.0.amount.value', '1.00');
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($details);
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame('evidence_mismatch', $payment->fresh()->verification_error_category);
        $this->assertTrue($payment->fresh()->verification_review_required);
        $this->assertDatabaseMissing('entitlements', ['media_id' => $media->id]);
    }

    public function test_gateway_timeout_is_retryable_and_backoff_suppresses_immediate_repeat(): void
    {
        [, $payment] = $this->walletDeposit();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andThrow(new RuntimeException('temporary connection failure'));
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame(1, $payment->fresh()->verification_attempts);
        $this->assertFalse($payment->fresh()->verification_review_required);
        $this->assertSame('provider_lookup_error', $payment->fresh()->verification_error_category);
    }

    public function test_retry_exhaustion_flags_review_without_more_provider_calls(): void
    {
        [, $payment] = $this->walletDeposit();
        $payment->update(['verification_attempts' => 11]);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andThrow(new RuntimeException('provider unavailable'));
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame(12, $payment->fresh()->verification_attempts);
        $this->assertTrue($payment->fresh()->verification_review_required);
        $this->assertNull($payment->fresh()->next_verification_at);
    }

    public function test_provider_error_response_retries_and_voided_order_is_cancelled(): void
    {
        [, $payment] = $this->walletDeposit();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn(['name' => 'RATE_LIMIT_REACHED', 'message' => 'Try again']);
        $this->app->instance(PayPalGateway::class, $gateway);
        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertFalse($payment->fresh()->verification_review_required);
        $this->assertSame('provider_rate_limited', $payment->fresh()->verification_error_category);
        $payment->update(['next_verification_at' => now()->subSecond()]);

        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'VOIDED', false));
        $this->app->instance(PayPalGateway::class, $gateway);
        $this->assertSame('cancelled', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('cancelled', $payment->order->fresh()->status);
    }

    public function test_provider_authentication_error_never_credits_wallet(): void
    {
        [$owner, $payment] = $this->walletDeposit();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn([
            'name' => 'AUTHENTICATION_FAILURE', 'http_status' => 401,
        ]);
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->assertSame('pending', app(PayPalPaymentVerification::class)->verify($payment));
        $this->assertSame('provider_authentication', $payment->fresh()->verification_error_category);
        $this->assertSame(0, $owner->wallet->fresh()->balance_minor);
    }

    public function test_status_routes_require_owner_before_gateway_lookup(): void
    {
        [, $payment] = $this->walletDeposit();
        $other = User::factory()->create();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldNotReceive('showOrderDetails');
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->getJson('/api/v1/wallet/order-status/'.$payment->order_id)->assertUnauthorized();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/wallet/order-status/'.$payment->order_id)->assertNotFound();
    }

    private function purchase(): array
    {
        $owner = User::factory()->create();
        $seller = User::factory()->create();
        Wallet::create(['user_id' => $seller->id, 'amount' => '0.00', 'balance_minor' => 0, 'currency' => 'USD']);
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);
        $media = EventsImges::create(['event_id' => $event->id, 'is_active' => '1', 'price' => '10.00']);
        $order = Purchases::create(['user_id' => $owner->id, 'payment_method' => 'paypal', 'type' => 'checkout',
            'order_type' => 'single_media', 'status' => 'pending', 'currency' => 'USD', 'amount' => '10.00',
            'amount_minor' => 1000, 'idempotency_key' => 'verification-order-'.uniqid()]);
        $order->items()->create(['image_id' => $media->id, 'price' => '10.00', 'purchased_type' => 'single']);
        $payment = $this->payment($order, 'purchase');

        return [$owner, $payment, $media];
    }

    private function walletDeposit(): array
    {
        $owner = User::factory()->create();
        Wallet::create(['user_id' => $owner->id, 'amount' => '0.00', 'balance_minor' => 0, 'currency' => 'USD']);
        $order = Purchases::create(['user_id' => $owner->id, 'payment_method' => 'paypal', 'type' => 'wallet_deposit',
            'order_type' => 'wallet_deposit', 'status' => 'pending', 'currency' => 'USD', 'amount' => '10.00',
            'amount_minor' => 1000, 'idempotency_key' => 'verification-wallet-'.uniqid()]);

        return [$owner, $this->payment($order, 'wallet_deposit')];
    }

    private function payment(Purchases $order, string $operation): Payment
    {
        $payment = Payment::create(['order_id' => $order->id, 'user_id' => $order->user_id,
            'operation' => $operation, 'method' => 'paypal', 'status' => 'pending', 'amount_minor' => 1000,
            'currency' => 'USD', 'idempotency_key' => 'verification-payment-'.uniqid(),
            'paypal_order_id' => 'PAYPAL-VERIFY-'.$order->id]);
        $prefix = $operation === 'wallet_deposit' ? 'wallet_topup' : 'purchase';
        $payment->update(['custom_id' => $prefix.':'.$payment->id, 'reference_id' => (string) $payment->id]);
        $order->update(['paypal_order_id' => $payment->paypal_order_id]);

        return $payment->fresh();
    }

    private function orderDetails(Payment $payment, string $status, bool $withCapture = true): array
    {
        $unit = ['reference_id' => $payment->reference_id, 'custom_id' => $payment->custom_id,
            'amount' => ['value' => '10.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => 'MERCHANT-VERIFY']];
        if ($withCapture) {
            $unit['payments'] = ['captures' => [['id' => 'CAPTURE-'.$payment->id, 'status' => 'COMPLETED',
                'amount' => ['value' => '10.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => 'MERCHANT-VERIFY']]]];
        }

        return ['id' => $payment->paypal_order_id, 'status' => $status, 'intent' => 'CAPTURE', 'purchase_units' => [$unit]];
    }

    private function captureEvent(Payment $payment): array
    {
        return ['id' => 'WH-VERIFY-'.$payment->id, 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAPTURE-'.$payment->id, 'status' => 'COMPLETED',
                'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
                'custom_id' => $payment->custom_id, 'reference_id' => $payment->reference_id,
                'payee' => ['merchant_id' => 'MERCHANT-VERIFY'],
                'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]]]];
    }

    private function webhookHeaders(): array
    {
        return ['PAYPAL-TRANSMISSION-ID' => 'test-transmission',
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'test-signature'];
    }
}

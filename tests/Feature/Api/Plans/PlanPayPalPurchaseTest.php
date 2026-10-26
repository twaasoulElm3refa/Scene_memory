<?php

namespace Tests\Feature\Api\Plans;

use App\Models\LicenceType;
use App\Models\Payment;
use App\Models\PlanFinancialSetting;
use App\Models\User;
use App\Models\UserCreditGrant;
use App\Services\PayPalGateway;
use App\Services\PayPalWebhookVerifier;
use App\Services\PlanFinancialEngine;
use App\Services\PlanQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The $200 "Pro" plan from the dev.scemory.com report: 31 photos and 6 videos,
 * on the seeded Basic/Pro/Premium tiers (payout caps are 90% of maximum prices).
 */
class PlanPayPalPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT = 'MERCHANT-PLAN';

    private User $buyer;

    private LicenceType $plan;

    private MockInterface $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config()->set('paypal.merchant_id', self::MERCHANT);
        config()->set('paypal.webhooks.checkout', 'checkout-plan-test');
        config()->set('paypal.webhooks.wallet', 'wallet-plan-test');
        $this->configurePlanFinancialSettings(['minimum_profit_minor' => 1, 'minimum_margin_bps' => 1200]);
        $this->plan = $this->proPlan();
        $this->buyer = User::factory()->create();
        $this->gateway = Mockery::mock(PayPalGateway::class);
        $this->app->instance(PayPalGateway::class, $this->gateway);
        Sanctum::actingAs($this->buyer);
    }

    public function test_checkout_returns_the_approval_url_at_the_top_level_for_the_200_pro_plan(): void
    {
        $this->expectOrderCreation();

        $response = $this->postJson($this->checkoutUrl())->assertOk();

        // show_plan.vue used to read response.data.data.approval_url, which never exists.
        $response->assertJsonPath('approval_url', $this->approvalLink());
        $response->assertJsonPath('status', 'created');
        $response->assertJsonMissingPath('data');
        $payment = Payment::where('order_id', $response->json('order_id'))->sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('plan:'.$this->plan->id, $payment->custom_id);
        $this->assertSame((string) $payment->order_id, $payment->reference_id);
        $this->assertSame('PAYPAL-PLAN-ORDER', $payment->paypal_order_id);
        $this->assertSame(20000, $payment->amount_minor);
        $this->assertDatabaseCount('user_credit_grants', 0);
    }

    public function test_repeated_checkout_reuses_the_open_paypal_order_instead_of_creating_another(): void
    {
        $this->expectOrderCreation();
        $first = $this->postJson($this->checkoutUrl())->assertOk();
        $payment = Payment::where('order_id', $first->json('order_id'))->sole();

        $this->gateway->shouldReceive('showOrderDetails')->once()->with('PAYPAL-PLAN-ORDER')
            ->andReturn($this->orderDetails($payment, 'CREATED', false) + ['links' => [['rel' => 'approve', 'href' => $this->approvalLink()]]]);
        $this->postJson($this->checkoutUrl())->assertOk()
            ->assertJsonPath('order_id', $first->json('order_id'))
            ->assertJsonPath('approval_url', $this->approvalLink())
            ->assertJsonPath('status', 'reused');

        // Once the buyer approved it at PayPal, the page is sent to confirmation instead.
        $this->gateway->shouldReceive('showOrderDetails')->once()->with('PAYPAL-PLAN-ORDER')
            ->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->postJson($this->checkoutUrl())->assertStatus(409)
            ->assertJsonPath('code', 'checkout_in_progress')
            ->assertJsonPath('order_id', $first->json('order_id'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_verified_capture_grants_the_saved_31_photo_6_video_quota_exactly_once(): void
    {
        $payment = $this->checkout();
        $this->gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldReceive('capturePaymentOrder')->once()
            ->with('PAYPAL-PLAN-ORDER', 'capture-plan-'.$payment->id)
            ->andReturn($this->orderDetails($payment, 'COMPLETED'));

        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('fulfillment_status', 'fulfilled');
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');

        $grant = UserCreditGrant::where('user_id', $this->buyer->id)->sole();
        $this->assertSame(31, $grant->photo_count);
        $this->assertSame(6, $grant->video_count);
        $this->assertSame(31, $grant->remaining_photos);
        $this->assertSame(6, $grant->remaining_videos);
        $this->assertSame($payment->order_id, $grant->purchase_id);
        $this->assertSame(20000, $grant->plan_price_paid_minor);
        $this->assertSame(
            [[1, 31, 6], [2, 10, 5]],
            collect($grant->tier_limits_snapshot)->map(fn ($limit) => [$limit['media_tier_id'], $limit['photo_maximum'], $limit['video_maximum']])->all(),
        );
        $this->assertSame(1, $grant->financial_assumptions_snapshot['settings_version']);
        $this->assertSame(['total_photos' => 31, 'total_videos' => 6], array_intersect_key(
            app(PlanQuotaService::class)->getUserCredits($this->buyer),
            ['total_photos' => 0, 'total_videos' => 0],
        ));

        // A completion webhook for the same capture, and its replay, change nothing.
        $this->trustWebhooks(2);
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-CAPTURE-1'))->assertOk()->assertJsonPath('status', 'ok');
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-CAPTURE-1'))->assertOk()->assertJsonPath('status', 'duplicate');
        $this->assertDatabaseCount('user_credit_grants', 1);
        $this->assertDatabaseCount('credit_transactions', 2);
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_webhook_first_settlement_grants_once_and_a_later_status_poll_is_a_noop(): void
    {
        $payment = $this->checkout();
        $this->gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldReceive('capturePaymentOrder')->once()->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->trustWebhooks(2);

        $this->webhook('/api/v1/paypal/webhook', $this->approvedEvent($payment))->assertOk()->assertJsonPath('status', 'ok');
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-CAPTURE-2'))->assertOk();
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseCount('user_credit_grants', 1);
        $this->assertDatabaseHas('user_credit_grants', ['purchase_id' => $payment->order_id, 'photo_count' => 31, 'video_count' => 6]);
    }

    public function test_pending_capture_is_never_treated_as_paid(): void
    {
        $payment = $this->checkout();
        $this->gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldReceive('capturePaymentOrder')->once()->andReturn(['status' => 'PENDING']);

        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('fulfillment_status', 'pending');
        $this->trustWebhooks(1);
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-PENDING', 'PAYMENT.CAPTURE.PENDING', 'PENDING'))->assertOk();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('user_credit_grants', 0);
    }

    public function test_denied_capture_fails_without_a_grant_and_is_never_recaptured(): void
    {
        $payment = $this->checkout();
        $this->trustWebhooks(1);
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-DENIED', 'PAYMENT.CAPTURE.DENIED', 'DECLINED'))->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('failed', $payment->order->fresh()->status);

        $this->gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldNotReceive('capturePaymentOrder');
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'failed');
        $this->assertDatabaseCount('user_credit_grants', 0);
    }

    public function test_a_late_denial_never_reverts_a_completed_plan_purchase(): void
    {
        $payment = $this->checkout();
        $this->trustWebhooks(2);
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-DONE'))->assertOk();
        $this->webhook('/api/v1/paypal/webhook', $this->captureEvent($payment, 'WH-LATE-DENIAL', 'PAYMENT.CAPTURE.DENIED', 'DECLINED'))->assertOk();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseCount('user_credit_grants', 1);
    }

    public function test_plan_rejected_by_the_persisted_financial_policy_never_reaches_paypal(): void
    {
        // Legacy Pro plan without tier ceilings: all 37 credits may be Pro media, a
        // worst case of $220.50 in creator payouts on a $200 sale.
        $legacy = LicenceType::create(['name' => 'Legacy Pro', 'slug' => 'legacy-pro', 'price' => '200.00',
            'media_tier_id' => 2, 'photo_quota' => 31, 'video_quota' => 6, 'is_active' => true]);
        $this->gateway->shouldNotReceive('createOrder');

        $this->postJson('/api/v1/plans/subscribe/'.$legacy->id.'/paypal')->assertStatus(422)
            ->assertJsonPath('code', 'plan_financially_unavailable');

        $analysis = app(PlanFinancialEngine::class)->analyzePlan($legacy);
        $this->assertSame(22050, $analysis['max_creator_liability_minor']);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_plan_that_becomes_unsafe_after_checkout_is_never_captured(): void
    {
        $payment = $this->checkout();
        // A new financial settings version raises the margin to 30% after the buyer started checkout.
        PlanFinancialSetting::query()->update(['is_active' => false]);
        $this->configurePlanFinancialSettings(['version' => 2, 'minimum_profit_minor' => 1, 'minimum_margin_bps' => 3000]);
        $this->gateway->shouldReceive('showOrderDetails')->twice()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldNotReceive('capturePaymentOrder');

        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'cancelled');
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'cancelled');
        $this->trustWebhooks(1);
        $this->webhook('/api/v1/paypal/webhook', $this->approvedEvent($payment))->assertOk();

        $payment->refresh();
        $this->assertSame('cancelled', $payment->status);
        $this->assertSame('plan_financially_unavailable', $payment->verification_error_category);
        $this->assertNull($payment->capture_id);
        $this->assertDatabaseCount('user_credit_grants', 0);
    }

    public function test_missing_merchant_configuration_blocks_checkout_before_the_buyer_can_approve(): void
    {
        config()->set('paypal.merchant_id', '');
        $this->gateway->shouldNotReceive('createOrder');

        // Outside debug mode every 5xx API body is replaced by a generic safe message;
        // the plan page maps the 503 status itself.
        $this->postJson($this->checkoutUrl())->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonMissingPath('approval_url');
        $this->assertDatabaseCount('payments', 0);

        config()->set('app.debug', true);
        $this->postJson($this->checkoutUrl())->assertStatus(503)->assertJsonPath('code', 'paypal_unavailable');
    }

    public function test_invalid_paypal_credentials_return_a_safe_error_and_log_only_the_provider_identity(): void
    {
        Log::spy();
        $this->gateway->shouldReceive('createOrder')->once()
            ->andReturn(['error' => ['name' => 'AUTHENTICATION_FAILURE', 'message' => 'Authentication failed', 'debug_id' => 'f00d']]);

        $response = $this->postJson($this->checkoutUrl())->assertStatus(502)
            ->assertJsonPath('status', 'error')
            ->assertJsonMissingPath('approval_url');

        $this->assertStringNotContainsString('AUTHENTICATION', $response->getContent());
        Log::shouldHaveReceived('warning')->with('plan_paypal_order_rejected', Mockery::on(
            fn (array $context) => $context['provider_error'] === 'AUTHENTICATION_FAILURE' && $context['debug_id'] === 'f00d'
        ));
        $this->assertDatabaseCount('user_credit_grants', 0);
    }

    public function test_missing_paypal_credentials_fail_safely_without_any_provider_call(): void
    {
        $this->app->forgetInstance(PayPalGateway::class);
        $this->app->offsetUnset(PayPalGateway::class);
        config()->set('paypal.mode', 'sandbox');
        config()->set('paypal.sandbox.client_id', '');
        config()->set('paypal.sandbox.client_secret', '');

        $this->postJson($this->checkoutUrl())->assertStatus(502)->assertJsonMissingPath('approval_url');
        $this->assertNull(Payment::sole()->paypal_order_id);
    }

    public function test_wallet_webhook_ignores_plan_capture_events(): void
    {
        $payment = $this->checkout();
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn(true);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);

        $this->webhook('/api/v1/wallet/webhook', $this->captureEvent($payment, 'WH-WALLET'))->assertOk()->assertJsonPath('status', 'ignored');

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertDatabaseCount('user_credit_grants', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_admin_created_plan_keeps_one_financial_policy_from_creation_to_grant(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $created = $this->postJson('/api/v1/plans/create', [
            'name' => 'Admin Pro', 'price' => 200, 'photo_quota' => 31, 'video_quota' => 6, 'is_active' => true,
            'tier_limits' => [
                ['media_tier_id' => 1, 'photo_maximum' => 31, 'video_maximum' => 6],
                ['media_tier_id' => 2, 'photo_maximum' => 10, 'video_maximum' => 5],
            ],
        ])->assertOk();
        $plan = LicenceType::findOrFail($created->json('data.id'));
        $this->assertSame('safe', $plan->financial_status);
        $this->assertNull($plan->financial_analysis['customer_value_target_bps']);

        Sanctum::actingAs($this->buyer);
        $this->plan = $plan;
        $payment = $this->checkout();
        $this->gateway->shouldReceive('showOrderDetails')->once()->andReturn($this->orderDetails($payment, 'APPROVED', false));
        $this->gateway->shouldReceive('capturePaymentOrder')->once()->andReturn($this->orderDetails($payment, 'COMPLETED'));
        $this->getJson('/api/v1/order/status/'.$payment->order_id)->assertOk()->assertJsonPath('status', 'completed');

        $grant = UserCreditGrant::sole();
        $stored = $plan->fresh()->financial_analysis;
        $this->assertSame([31, 6], [$grant->photo_count, $grant->video_count]);
        $this->assertSame($stored['settings_version'], $grant->financial_assumptions_snapshot['settings_version']);
        $this->assertSame($stored['max_creator_liability_minor'], $grant->financial_assumptions_snapshot['max_creator_liability_minor']);
        $this->assertSame($stored['worst_case_profit_minor'], $grant->financial_assumptions_snapshot['worst_case_profit_minor']);
    }

    public function test_value_target_plan_is_checked_against_its_110_percent_rule_at_checkout(): void
    {
        // A plan saved under the recommendation rule (potential value >= 110% of price)
        // keeps that rule at checkout; it is not silently judged by standard rules.
        $target = LicenceType::create(['name' => 'Value Pro', 'slug' => 'value-pro', 'price' => '20.00',
            'media_tier_id' => 1, 'photo_quota' => 9, 'video_quota' => 0, 'is_active' => true,
            'financial_analysis' => ['customer_value_target_bps' => 11000]]);
        $target->tierLimits()->create(['media_tier_id' => 1, 'photo_maximum' => 9, 'video_maximum' => 0]);
        $analysis = app(PlanFinancialEngine::class)->analyzePlan($target);
        $this->assertTrue($analysis['profit_requirement_is_valid']);
        $this->assertSame(2200, $analysis['minimum_customer_value_minor']);
        $this->assertFalse($analysis['catalog_value_is_valid']);
        $this->gateway->shouldNotReceive('createOrder');

        $this->postJson('/api/v1/plans/subscribe/'.$target->id.'/paypal')->assertStatus(422)
            ->assertJsonPath('code', 'plan_financially_unavailable');
    }

    private function proPlan(): LicenceType
    {
        $plan = LicenceType::create(['name' => 'Pro', 'slug' => 'pro-200', 'price' => '200.00', 'media_tier_id' => 2,
            'photo_quota' => 31, 'video_quota' => 6, 'is_active' => true]);
        $plan->tierLimits()->createMany([
            ['media_tier_id' => 1, 'photo_maximum' => 31, 'video_maximum' => 6],
            ['media_tier_id' => 2, 'photo_maximum' => 10, 'video_maximum' => 5],
        ]);
        $analysis = app(PlanFinancialEngine::class)->analyzePlan($plan);
        $this->assertTrue($analysis['is_valid'], implode(' ', $analysis['errors']));
        $this->assertSame(15480, $analysis['max_creator_liability_minor']);
        $plan->update(['financial_analysis' => $analysis, 'financial_status' => 'safe', 'financial_settings_version' => 1]);

        return $plan->fresh();
    }

    private function checkoutUrl(): string
    {
        return '/api/v1/plans/subscribe/'.$this->plan->id.'/paypal';
    }

    private function approvalLink(): string
    {
        return 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-PLAN-ORDER';
    }

    private function expectOrderCreation(): void
    {
        $this->gateway->shouldReceive('createOrder')->once()->withArgs(fn (array $request, string $requestId) => str_starts_with($requestId, 'plan-paypal:')
            && data_get($request, 'intent') === 'CAPTURE'
            && data_get($request, 'purchase_units.0.custom_id') === 'plan:'.$this->plan->id
            && data_get($request, 'purchase_units.0.amount.value') === '200.00'
            && data_get($request, 'purchase_units.0.amount.currency_code') === 'USD'
            && str_ends_with((string) data_get($request, 'application_context.return_url'), '/api/v1/paypal/success')
        )->andReturn(['id' => 'PAYPAL-PLAN-ORDER', 'status' => 'CREATED', 'links' => [['rel' => 'approve', 'href' => $this->approvalLink()]]]);
    }

    private function checkout(): Payment
    {
        $this->expectOrderCreation();
        $response = $this->postJson($this->checkoutUrl())->assertOk();

        return Payment::where('order_id', $response->json('order_id'))->sole();
    }

    private function orderDetails(Payment $payment, string $status, bool $withCapture = true): array
    {
        $unit = ['reference_id' => $payment->reference_id, 'custom_id' => $payment->custom_id,
            'amount' => ['value' => '200.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => self::MERCHANT]];
        if ($withCapture) {
            $unit['payments'] = ['captures' => [['id' => 'CAPTURE-PLAN-'.$payment->id, 'status' => 'COMPLETED',
                'amount' => ['value' => '200.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => self::MERCHANT]]]];
        }

        return ['id' => $payment->paypal_order_id, 'status' => $status, 'intent' => 'CAPTURE', 'purchase_units' => [$unit]];
    }

    private function captureEvent(Payment $payment, string $eventId, string $type = 'PAYMENT.CAPTURE.COMPLETED', string $status = 'COMPLETED'): array
    {
        return ['id' => $eventId, 'event_type' => $type,
            'resource' => ['id' => 'CAPTURE-PLAN-'.$payment->id, 'status' => $status,
                'amount' => ['value' => '200.00', 'currency_code' => 'USD'],
                'custom_id' => $payment->custom_id, 'reference_id' => $payment->reference_id,
                'payee' => ['merchant_id' => self::MERCHANT],
                'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]]]];
    }

    private function approvedEvent(Payment $payment): array
    {
        return ['id' => 'WH-APPROVED-'.$payment->id, 'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => $payment->paypal_order_id, 'status' => 'APPROVED', 'intent' => 'CAPTURE',
                'purchase_units' => [['reference_id' => $payment->reference_id, 'custom_id' => $payment->custom_id,
                    'amount' => ['value' => '200.00', 'currency_code' => 'USD']]]]];
    }

    private function trustWebhooks(int $times): void
    {
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->times($times)->andReturn(true);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);
    }

    private function webhook(string $url, array $event)
    {
        return $this->withHeaders([
            'PAYPAL-TRANSMISSION-ID' => 'transmission-'.$event['id'],
            'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
            'PAYPAL-CERT-URL' => 'https://api-m.sandbox.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'test-signature',
        ])->postJson($url, $event);
    }
}

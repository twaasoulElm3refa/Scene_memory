<?php

namespace Tests\Feature\Api\Payments;

use App\Models\FinancialHold;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransactions;
use App\Services\PayPalWebhookVerifier;
use App\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class PaymentReversalHoldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('paypal.merchant_id', 'MERCHANT-REVERSAL');
        config()->set('paypal.webhooks.checkout', 'checkout-reversal-test');
        config()->set('paypal.webhooks.wallet', 'wallet-reversal-test');
        $verifier = Mockery::mock(PayPalWebhookVerifier::class);
        $verifier->shouldReceive('verify')->andReturn(true);
        $this->app->instance(PayPalWebhookVerifier::class, $verifier);
    }

    public function test_refunded_top_up_is_held_and_cannot_be_withdrawn_until_an_admin_releases_it(): void
    {
        [$user, $wallet, $payment] = $this->settledTopUp(10000);

        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($payment, 'REFUND-1', '100.00', 'WH-R1'))->assertOk();

        $hold = FinancialHold::sole();
        $this->assertSame([$wallet->id, 10000, 'refund', 'active'], [$hold->wallet_id, $hold->amount_minor, $hold->reason, $hold->status]);
        $this->assertTrue($payment->fresh()->verification_review_required);
        // History and balances are preserved: a hold is not a ledger movement.
        $this->assertSame(10000, $wallet->fresh()->balance_minor);
        $this->assertSame(0, WalletTransactions::count());

        Sanctum::actingAs($user);
        $this->withdraw($wallet, '50.00')->assertStatus(422);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/financial-holds/'.$hold->id.'/release', ['note' => 'Refund recovered by bank transfer'])->assertOk()
            ->assertJsonPath('data.status', 'released');
        Sanctum::actingAs($user);
        $this->withdraw($wallet, '50.00')->assertOk();
    }

    public function test_duplicate_and_cross_webhook_deliveries_of_one_refund_create_one_hold(): void
    {
        [, , $payment] = $this->settledTopUp(10000);
        $event = $this->refundEvent($payment, 'REFUND-DUP', '100.00', 'WH-DUP');

        $this->webhook('/api/v1/wallet/webhook', $event)->assertOk();
        $this->webhook('/api/v1/wallet/webhook', $event)->assertOk()->assertJsonPath('status', 'duplicate');
        $this->webhook('/api/v1/paypal/webhook', $event)->assertOk();
        $this->webhook('/api/v1/wallet/webhook', ['id' => 'WH-DUP-RETRY'] + $event)->assertOk();

        $this->assertSame(1, FinancialHold::count());
        $this->assertSame(10000, FinancialHold::sole()->amount_minor);
    }

    public function test_partial_refunds_accumulate_but_never_exceed_what_the_payment_credited(): void
    {
        [, $wallet, $payment] = $this->settledTopUp(10000);

        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($payment, 'REFUND-A', '30.00', 'WH-A'))->assertOk();
        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($payment, 'REFUND-B', '80.00', 'WH-B'))->assertOk();

        $this->assertSame([3000, 7000], FinancialHold::orderBy('id')->pluck('amount_minor')->all());
        $this->assertSame(10000, FinancialHold::heldMinor($wallet->id));
    }

    public function test_purchase_refund_holds_only_each_sellers_proceeds_from_that_payment(): void
    {
        [$payment, $sellerA, $sellerB] = $this->settledPurchase();

        $this->webhook('/api/v1/paypal/webhook', $this->refundEvent($payment, 'REFUND-HALF', '15.00', 'WH-HALF'))->assertOk();

        // 15.00 of 30.00 refunded: half of each seller's credit, rounded up.
        $this->assertSame(425, FinancialHold::heldMinor($sellerA->id));
        $this->assertSame(850, FinancialHold::heldMinor($sellerB->id));
        $this->assertSame(0, FinancialHold::where('user_id', $payment->user_id)->count());

        $this->webhook('/api/v1/paypal/webhook', $this->refundEvent($payment, 'REFUND-REST', '15.00', 'WH-REST'))->assertOk();
        $this->assertSame(850, FinancialHold::heldMinor($sellerA->id));
        $this->assertSame(1700, FinancialHold::heldMinor($sellerB->id));

        // Seller A's unrelated earnings (50.00 before this sale) stay withdrawable.
        Sanctum::actingAs($sellerA->user);
        $this->withdraw($sellerA, '50.00')->assertOk();
        $this->withdraw($sellerA, '0.01')->assertStatus(422);
    }

    public function test_dispute_holds_follow_the_paypal_outcome_without_double_counting(): void
    {
        [, $wallet, $payment] = $this->settledTopUp(10000);
        $this->webhook('/api/v1/wallet/webhook', $this->disputeEvent($payment, 'CUSTOMER.DISPUTE.CREATED', 'PP-D-1', 'WH-D1'))->assertOk();
        $this->assertSame(10000, FinancialHold::heldMinor($wallet->id));

        // A reversal while the dispute hold is active adds nothing on top of it.
        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($payment, 'REV-1', '100.00', 'WH-REV', 'PAYMENT.CAPTURE.REVERSED'))->assertOk();
        $this->assertSame(10000, FinancialHold::heldMinor($wallet->id));

        $this->webhook('/api/v1/wallet/webhook', $this->disputeEvent($payment, 'CUSTOMER.DISPUTE.RESOLVED', 'PP-D-1', 'WH-D2', 'RESOLVED_SELLER_FAVOUR'))->assertOk();
        $dispute = FinancialHold::where('reason', 'dispute')->sole();
        $this->assertSame('released', $dispute->status);
        $this->assertSame('RESOLVED_SELLER_FAVOUR', $dispute->metadata['dispute_outcome']);

        [, $other, $second] = $this->settledTopUp(5000);
        $this->webhook('/api/v1/wallet/webhook', $this->disputeEvent($second, 'CUSTOMER.DISPUTE.CREATED', 'PP-D-2', 'WH-D3', null, '50.00'))->assertOk();
        $this->webhook('/api/v1/wallet/webhook', $this->disputeEvent($second, 'CUSTOMER.DISPUTE.RESOLVED', 'PP-D-2', 'WH-D4', 'RESOLVED_BUYER_FAVOUR', '50.00'))->assertOk();
        $this->assertSame(5000, FinancialHold::heldMinor($other->id));
    }

    public function test_plan_refunds_only_flag_review_and_unsettled_payments_are_ignored(): void
    {
        [, $wallet, $plan] = $this->settledTopUp(20000, 'plan_subscription');
        $this->webhook('/api/v1/paypal/webhook', $this->refundEvent($plan, 'REFUND-PLAN', '200.00', 'WH-PLAN'))->assertOk();
        $this->assertSame(0, FinancialHold::count());
        $this->assertTrue($plan->fresh()->verification_review_required);

        [, , $pending] = $this->settledTopUp(10000);
        $pending->update(['status' => 'pending']);
        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($pending, 'REFUND-EARLY', '100.00', 'WH-EARLY'))->assertOk();
        $this->assertSame(0, FinancialHold::count());
        $this->assertSame(20000, $wallet->fresh()->balance_minor);
    }

    public function test_late_pending_or_denied_events_never_downgrade_a_completed_payment(): void
    {
        [, , $topUp] = $this->settledTopUp(10000);
        [$purchase] = $this->settledPurchase();

        foreach ([['/api/v1/wallet/webhook', $topUp], ['/api/v1/paypal/webhook', $purchase]] as [$url, $payment]) {
            foreach (['PAYMENT.CAPTURE.PENDING' => 'PENDING', 'PAYMENT.CAPTURE.DENIED' => 'DECLINED'] as $type => $status) {
                $this->webhook($url, $this->captureEvent($payment, $type, $status, 'WH-'.$type.'-'.$payment->id))->assertOk();
                $this->assertSame('completed', $payment->fresh()->status, $type);
                $this->assertSame('completed', $payment->order->fresh()->status, $type);
            }
        }
    }

    public function test_withdrawal_fee_comes_from_the_server_rule_not_the_requester(): void
    {
        config()->set('wallet.withdrawal_fee_fixed_minor', 100);
        config()->set('wallet.withdrawal_fee_bps', 250);
        [$user, $wallet] = $this->settledTopUp(10000);
        Sanctum::actingAs($user);

        $created = $this->withdraw($wallet, '40.00', ['fee' => '0.00'])->assertOk();
        // 1.00 fixed + 2.5% of 40.00.
        $this->assertSame('2.00', $created->json('data.fee'));
        $this->assertSame('38.00', $created->json('data.net_amount'));

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);
        $updated = $this->postJson('/api/v1/withdraw/updateWithdrawals/'.$created->json('data.id'), ['fee' => '0.50'])->assertOk();
        // Compared numerically: SQLite returns re-read DECIMAL columns as floats.
        $this->assertEquals(0.5, (float) $updated->json('data.fee'));
    }

    public function test_only_administrators_can_review_holds(): void
    {
        [$user, , $payment] = $this->settledTopUp(10000);
        $this->webhook('/api/v1/wallet/webhook', $this->refundEvent($payment, 'REFUND-ADM', '100.00', 'WH-ADM'))->assertOk();

        $this->getJson('/api/v1/admin/financial-holds')->assertUnauthorized();
        // AdminMiddleware answers non-administrators with 401, like every other admin route.
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/financial-holds')->assertUnauthorized();
        $this->postJson('/api/v1/admin/financial-holds/'.FinancialHold::sole()->id.'/release', ['note' => 'mine'])->assertUnauthorized();
        $this->assertSame('active', FinancialHold::sole()->status);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->getJson('/api/v1/admin/financial-holds?status=active')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_a_repeated_ledger_post_returns_the_original_entry_without_moving_money_twice(): void
    {
        [$user, $wallet] = $this->settledTopUp(10000);
        $order = Purchases::create(['user_id' => $user->id, 'payment_method' => 'wallet', 'type' => 'checkout', 'order_type' => 'single_media',
            'status' => 'completed', 'currency' => 'USD', 'amount' => '5.00', 'amount_minor' => 500, 'idempotency_key' => 'ledger-'.uniqid()]);
        $ledger = app(WalletLedgerService::class);

        $first = $ledger->post($wallet->fresh(), $order, null, 'debit', 'content_purchase', 500, 'ledger-key-1', 'order:'.$order->id);
        $second = $ledger->post($wallet->fresh(), $order, null, 'debit', 'content_purchase', 500, 'ledger-key-1', 'order:'.$order->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(9500, $wallet->fresh()->balance_minor);
        $this->assertSame(1, WalletTransactions::count());
    }

    /**
     * @return array{0: User, 1: Wallet, 2: Payment}
     */
    private function settledTopUp(int $amountMinor, string $operation = 'wallet_deposit'): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $wallet = Wallet::create(['user_id' => $user->id, 'amount' => number_format($amountMinor / 100, 2, '.', ''), 'balance_minor' => $amountMinor, 'currency' => 'USD']);

        return [$user, $wallet, $this->settledPayment($user, $amountMinor, $operation)];
    }

    /**
     * Buyer pays 30.00 for two items; sellers were credited 8.50 and 17.00.
     *
     * @return array{0: Payment, 1: Wallet, 2: Wallet}
     */
    private function settledPurchase(): array
    {
        $buyer = User::factory()->create(['is_active' => true]);
        $payment = $this->settledPayment($buyer, 3000, 'purchase');
        $ledger = app(WalletLedgerService::class);
        $sellers = [];
        foreach ([[5000, 850], [0, 1700]] as $index => [$earlier, $credit]) {
            $seller = User::factory()->create(['is_active' => true]);
            $wallet = Wallet::create(['user_id' => $seller->id, 'amount' => number_format($earlier / 100, 2, '.', ''), 'balance_minor' => $earlier, 'currency' => 'USD']);
            $ledger->post($wallet, $payment->order, null, 'credit', 'media_sale', $credit, 'seller-credit:'.$payment->id.':'.($index + 1), 'payment:'.$payment->id);
            $sellers[] = $wallet->fresh();
        }

        return [$payment, ...$sellers];
    }

    private function settledPayment(User $user, int $amountMinor, string $operation): Payment
    {
        $order = Purchases::create(['user_id' => $user->id, 'payment_method' => 'paypal', 'type' => $operation === 'purchase' ? 'checkout' : $operation,
            'order_type' => $operation === 'purchase' ? 'cart' : $operation, 'status' => 'completed', 'currency' => 'USD',
            'amount' => number_format($amountMinor / 100, 2, '.', ''), 'amount_minor' => $amountMinor, 'idempotency_key' => 'order-'.uniqid()]);
        $payment = Payment::create(['order_id' => $order->id, 'user_id' => $user->id, 'operation' => $operation, 'method' => 'paypal',
            'status' => 'completed', 'amount_minor' => $amountMinor, 'currency' => 'USD', 'idempotency_key' => 'pay-'.uniqid(),
            'paypal_order_id' => 'ORDER-'.uniqid(), 'capture_id' => 'CAPTURE-'.uniqid(), 'paid_at' => now()]);
        $prefix = ['purchase' => 'purchase', 'wallet_deposit' => 'wallet_topup', 'plan_subscription' => 'plan'][$operation];
        $payment->update(['custom_id' => $prefix.':'.$payment->id, 'reference_id' => (string) $payment->id]);
        $order->update(['paypal_order_id' => $payment->paypal_order_id]);

        return $payment->fresh();
    }

    private function refundEvent(Payment $payment, string $refundId, string $value, string $eventId, string $type = 'PAYMENT.CAPTURE.REFUNDED'): array
    {
        return ['id' => $eventId, 'event_type' => $type, 'resource' => [
            'id' => $refundId, 'status' => 'COMPLETED', 'amount' => ['value' => $value, 'currency_code' => 'USD'],
            'supplementary_data' => ['related_ids' => ['capture_id' => $payment->capture_id, 'order_id' => $payment->paypal_order_id]],
        ]];
    }

    private function disputeEvent(Payment $payment, string $type, string $disputeId, string $eventId, ?string $outcome = null, string $value = '100.00'): array
    {
        $resource = ['dispute_id' => $disputeId, 'dispute_amount' => ['value' => $value, 'currency_code' => 'USD'],
            'disputed_transactions' => [['seller_transaction_id' => $payment->capture_id]],
            'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]]];
        if ($outcome) {
            $resource['dispute_outcome'] = ['outcome_code' => $outcome];
        }

        return ['id' => $eventId, 'event_type' => $type, 'resource' => $resource];
    }

    private function captureEvent(Payment $payment, string $type, string $status, string $eventId): array
    {
        return ['id' => $eventId, 'event_type' => $type, 'resource' => [
            'id' => $payment->capture_id, 'status' => $status,
            'amount' => ['value' => number_format($payment->amount_minor / 100, 2, '.', ''), 'currency_code' => 'USD'],
            'custom_id' => $payment->custom_id, 'reference_id' => $payment->reference_id,
            'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]],
        ]];
    }

    private function withdraw(Wallet $wallet, string $amount, array $extra = [])
    {
        return $this->postJson('/api/v1/withdraw/requestWithdrawals/'.$wallet->id, ['amount' => $amount, 'currency' => 'USD', 'method' => 'bank'] + $extra);
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

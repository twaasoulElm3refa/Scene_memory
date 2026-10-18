<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Comments;
use App\Models\Events;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\GiftWithdrawal;
use App\Models\User;
use App\Models\VirtualGift;
use App\Models\Wallet;
use App\Services\GiftCommission;
use App\Services\GiftEarningsService;
use App\Services\PaymentFinalizer;
use App\Services\PayPalGateway;
use App\Services\PayPalServices;
use App\Services\PayPalWebhookVerifier;
use App\Services\VirtualGiftService;
use Database\Seeders\VirtualGiftSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class VirtualGiftsTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;

    private User $recipient;

    private Events $event;

    private VirtualGift $gift;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['paypal.mode' => 'sandbox', 'paypal.merchant_id' => 'MERCHANT-TEST', 'paypal.webhooks.checkout' => 'webhook-test', 'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
        $this->sender = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->recipient = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->event = Events::create(['user_id' => $this->recipient->id, 'title' => 'Gift event', 'slug' => 'gift-event', 'is_active' => 1]);
        $this->gift = VirtualGift::create(['slug' => 'coffee', 'name' => 'Coffee', 'price_minor' => 100, 'currency' => 'USD']);
        Wallet::create(['user_id' => $this->sender->id, 'currency' => 'USD', 'amount' => '100.00', 'balance_minor' => 10000]);
        Sanctum::actingAs($this->sender);
    }

    private function input(array $overrides = []): array
    {
        return array_merge(['gift_id' => $this->gift->id, 'event_id' => $this->event->id, 'quantity' => 1, 'idempotency_key' => 'test-gift-idempotency-123'], $overrides);
    }

    private function paid(): GiftTransaction
    {
        return app(VirtualGiftService::class)->sendWallet($this->sender->id, $this->input());
    }

    private function captureResource(GiftTransaction $gift, array $overrides = []): array
    {
        return array_replace_recursive(['id' => 'CAPTURE-GIFT', 'status' => 'COMPLETED', 'custom_id' => $gift->payment->custom_id, 'amount' => ['value' => '1.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => 'MERCHANT-TEST'], 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-GIFT']], 'seller_receivable_breakdown' => ['paypal_fee' => ['value' => '0.60', 'currency_code' => 'USD']]], $overrides);
    }

    private function paypalGift(): GiftTransaction
    {
        $gift = app(VirtualGiftService::class)->create($this->sender->id, $this->input(), 'paypal');
        $gift->update(['paypal_order_id' => 'ORDER-GIFT']);
        $gift->payment->update(['paypal_order_id' => 'ORDER-GIFT']);
        $gift->payment->order->update(['paypal_order_id' => 'ORDER-GIFT']);

        return $gift->fresh();
    }

    private function webhook(array $payload)
    {
        $this->instance(PayPalWebhookVerifier::class, Mockery::mock(PayPalWebhookVerifier::class)->shouldReceive('verify')->andReturn(true)->getMock());

        return $this->postJson('/api/v1/paypal/webhook', $payload);
    }

    public function test_wallet_gift_debits_once_creates_entitlement_and_does_not_award_ranking_points(): void
    {
        $this->postJson('/api/gifts/send/wallet', $this->input(['quantity' => 3]))->assertOk()->assertJsonPath('data.gross_amount_minor', 300)->assertJsonPath('data.platform_commission_minor', 45)->assertJsonPath('data.recipient_earning_minor', 255)->assertJsonPath('data.delivery_status', 'delivered');
        $this->postJson('/api/gifts/send/wallet', $this->input(['quantity' => 3]))->assertOk();
        $this->assertSame(9700, $this->sender->wallet->fresh()->balance_minor);
        $this->assertDatabaseCount('gift_transactions', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(255, GiftEarningEntry::sole()->pending_minor);
        $this->assertSame('not_applicable', GiftTransaction::sole()->processing_fee_status);
        $this->assertNull(GiftTransaction::sole()->payment_processing_fee_minor);
        $this->assertSame(0, $this->sender->fresh()->total_points);
        $this->assertSame(0, $this->recipient->fresh()->total_points);
        $this->assertDatabaseCount('user_weekly_points', 0);
        $this->assertDatabaseCount('user_points_history', 0);
    }

    public function test_server_validates_price_recipient_relationships_and_idempotency_payload(): void
    {
        $this->postJson('/api/gifts/send/wallet', $this->input(['price_minor' => 1]))->assertUnprocessable();
        $this->postJson('/api/gifts/send/wallet', $this->input(['recipient_user_id' => $this->sender->id]))->assertUnprocessable();
        $this->postJson('/api/gifts/send/wallet', $this->input(['quantity' => 101]))->assertUnprocessable();
        $this->paid();
        $this->postJson('/api/gifts/send/wallet', $this->input(['quantity' => 2]))->assertConflict();
        $other = Events::create(['user_id' => $this->recipient->id, 'is_active' => 1]);
        $comment = Comments::create(['event_id' => $other->id, 'user_id' => $this->recipient->id, 'comment' => 'Hello']);
        $this->postJson('/api/gifts/send/wallet', $this->input(['comment_id' => $comment->id, 'idempotency_key' => 'different-gift-key-1234']))->assertNotFound();
    }

    public function test_comment_author_is_derived_and_event_and_comment_summaries_are_separate(): void
    {
        $author = User::factory()->create(['is_active' => true]);
        $comment = Comments::create(['event_id' => $this->event->id, 'user_id' => $author->id, 'comment' => 'Hello']);
        $this->postJson('/api/gifts/send/wallet', $this->input(['comment_id' => $comment->id]))->assertOk()->assertJsonPath('data.recipient.id', $author->id);
        $this->getJson('/api/events/'.$this->event->id.'/gifts/summary')->assertJsonCount(0, 'data');
        $this->getJson('/api/comments/'.$comment->id.'/gifts/summary')->assertJsonPath('data.0.quantity', 1);
    }

    public function test_insufficient_balance_or_currency_mismatch_rolls_back_every_financial_record(): void
    {
        $this->sender->wallet->update(['balance_minor' => 99, 'amount' => '0.99']);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('gift_transactions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('purchases', 0);
        $this->sender->wallet->update(['balance_minor' => 10000, 'amount' => '100.00', 'currency' => 'EUR']);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_self_gifting_inactive_gifts_and_deleted_or_hidden_targets_are_rejected(): void
    {
        $this->event->update(['user_id' => $this->sender->id]);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertUnprocessable();
        $this->event->update(['user_id' => $this->recipient->id, 'is_active' => 0]);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertNotFound();
        $this->event->update(['is_active' => 1]);
        $this->gift->update(['is_active' => false]);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertNotFound();
        $this->gift->update(['is_active' => true]);
        $comment = Comments::create(['event_id' => $this->event->id, 'user_id' => $this->recipient->id]);
        $comment->delete();
        $this->postJson('/api/gifts/send/wallet', $this->input(['comment_id' => $comment->id]))->assertNotFound();
    }

    public function test_commission_uses_half_up_rounding_on_total_with_no_float_math(): void
    {
        $calculator = app(GiftCommission::class);
        foreach ([100 => 15, 300 => 45, 500 => 75, 1000 => 150, 2500 => 375, 10000 => 1500, 1 => 0, 10 => 2, 30 => 5] as $gross => $expected) {
            $split = $calculator->split($gross);
            $this->assertSame($expected, $split['commission']);
            $this->assertSame($gross, $split['commission'] + $split['recipient']);
        }
        $this->gift->update(['price_minor' => 1]);
        $gift = app(VirtualGiftService::class)->sendWallet($this->sender->id, $this->input(['quantity' => 10]));
        $this->assertSame(2, $gift->platform_commission_minor);
    }

    public function test_catalog_seeder_is_idempotent_and_snapshots_survive_admin_edits(): void
    {
        $gift = $this->paid();
        $this->seed(VirtualGiftSeeder::class);
        $this->gift->update(['name' => 'Custom coffee', 'price_minor' => 999, 'is_active' => false]);
        $this->seed(VirtualGiftSeeder::class);
        $this->assertSame(999, $this->gift->fresh()->price_minor);
        $this->assertFalse($this->gift->fresh()->is_active);
        $this->assertSame('Coffee', $gift->fresh()->gift_snapshot['name']);
        $this->assertSame(100, $gift->fresh()->unit_price_minor);
        $this->assertDatabaseCount('virtual_gifts', 5);
    }

    public function test_paypal_orders_reuse_gateway_with_server_amount_and_idempotency_header(): void
    {
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->withArgs(fn ($data, $key) => $data['purchase_units'][0]['amount']['value'] === '1.00' && str_starts_with($data['purchase_units'][0]['custom_id'], 'virtual_gift:') && str_starts_with($key, 'gift-create-'))->andReturn(['id' => 'ORDER-GIFT', 'status' => 'CREATED', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-GIFT']]]);
        $this->instance(PayPalGateway::class, $gateway);
        $this->postJson('/api/gifts/paypal/create-order', $this->input())->assertOk()->assertJsonPath('data.transaction.payment_status', 'pending');
        $this->assertDatabaseCount('gift_earning_entries', 0);
    }

    public function test_verified_capture_delivers_once_and_records_actual_platform_fee_even_above_commission(): void
    {
        $gift = $this->paypalGift();
        $resource = $this->captureResource($gift);
        $this->webhook(['id' => 'WH-GIFT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $resource])->assertOk();
        $this->webhook(['id' => 'WH-GIFT-1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $resource])->assertOk()->assertJsonPath('status', 'duplicate');
        $this->webhook(['id' => 'WH-GIFT-2', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $resource])->assertOk();
        $this->assertSame(60, $gift->fresh()->payment_processing_fee_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->pending_minor);
        $this->assertDatabaseCount('gift_earning_entries', 1);
        $this->assertDatabaseCount('gift_earning_movements', 1);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/gifts/report')->assertOk()->assertJsonPath('data.financials.0.platform_net_minor', -45);
    }

    public function test_capture_rejects_wrong_amount_currency_merchant_and_order_without_delivery(): void
    {
        $gift = $this->paypalGift();
        foreach ([['amount' => ['value' => '2.00']], ['amount' => ['currency_code' => 'EUR']], ['payee' => ['merchant_id' => 'OTHER']], ['supplementary_data' => ['related_ids' => ['order_id' => 'OTHER']]]] as $index => $override) {
            $this->webhook(['id' => 'WH-WRONG-'.$index, 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $this->captureResource($gift, $override)])->assertStatus(500);
        }
        $this->assertDatabaseCount('gift_earning_entries', 0);
        $this->assertSame('pending', $gift->fresh()->delivery_status);
    }

    public function test_unknown_fee_remains_null_and_net_revenue_is_provisional(): void
    {
        $gift = $this->paypalGift();
        $resource = $this->captureResource($gift);
        unset($resource['seller_receivable_breakdown']);
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $resource);
        $this->assertNull($gift->fresh()->payment_processing_fee_minor);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/gifts/report')->assertJsonPath('data.financials.0.platform_net_minor', null)->assertJsonPath('data.financials.0.net_is_provisional', true);
    }

    public function test_holding_period_release_and_withdrawal_are_distinct_and_idempotent(): void
    {
        $gift = $this->paid();
        $this->assertSame($gift->paid_at->copy()->addHours(24)->toDateTimeString(), $gift->release_at->toDateTimeString());
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->pending_minor);
        $this->travel(23)->hours();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->pending_minor);
        $this->travel(1)->hours();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->available_minor);
        $this->assertNull($this->recipient->wallet);
        Sanctum::actingAs($this->recipient);
        $input = ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'withdrawal-test-key-123', 'payment_details' => ['method' => 'manual', 'destination' => 'Private payout address']];
        $this->postJson('/api/gifts/withdrawals', $input)->assertOk();
        $this->postJson('/api/gifts/withdrawals', $input)->assertOk();
        $withdrawal = GiftWithdrawal::sole();
        $this->assertStringNotContainsString('Private payout address', DB::table('gift_withdrawals')->sole()->payment_details);
        $this->assertSame(0, GiftEarningEntry::sole()->settled_minor);
        $earnings = app(GiftEarningsService::class);
        $earnings->changeWithdrawal($withdrawal, 'approved', $this->sender->id, null);
        $earnings->changeWithdrawal($withdrawal, 'paid', $this->sender->id, 'EXTERNAL-TEST-PAYOUT');
        $earnings->changeWithdrawal($withdrawal, 'paid', $this->sender->id, 'EXTERNAL-TEST-PAYOUT');
        $this->assertSame(85, GiftEarningEntry::sole()->settled_minor);
    }

    public function test_existing_pending_release_timestamp_is_preserved(): void
    {
        $gift = $this->paid();
        $legacyRelease = $gift->paid_at->copy()->addDays(7);
        GiftEarningEntry::sole()->update(['release_at' => $legacyRelease]);
        $gift->update(['release_at' => $legacyRelease]);

        $this->travel(25)->hours();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->pending_minor);
        $this->travel(6)->days();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->available_minor);
    }

    public function test_admin_withdrawal_review_keeps_reservation_until_confirmed_payout(): void
    {
        $gift = $this->paid();
        $this->getJson('/api/gifts/sent')->assertJsonPath('data.data.0.event_title', 'Gift event');
        $this->getJson('/api/gifts/earnings')->assertJsonCount(0, 'data.balances');
        Sanctum::actingAs($this->recipient);
        $this->getJson('/api/gifts/earnings')->assertJsonPath('data.balances.0.total_received_minor', 100)->assertJsonPath('data.balances.0.withdrawable_minor', 0);
        $this->travel(25)->hours();
        app(GiftEarningsService::class)->release();
        Sanctum::actingAs($this->recipient);
        $this->getJson('/api/gifts/sent')->assertJsonCount(0, 'data.data');
        $this->getJson('/api/gifts/earnings')->assertJsonPath('data.balances.0.withdrawable_minor', 85);
        $this->postJson('/api/gifts/withdrawals', ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'review-flow-withdrawal-123', 'payment_details' => ['method' => 'manual', 'destination' => 'Private destination']])->assertOk();
        $withdrawal = GiftWithdrawal::sole();
        $this->assertSame(85, $withdrawal->available_balance_at_request_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->reserved_minor);
        $this->getJson('/api/v1/admin/gifts/withdrawals')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/gifts/withdrawals')->assertOk()->assertJsonMissingPath('data.data.0.payment_details')->assertJsonPath('data.data.0.destination_masked', '••••');
        $url = '/api/v1/admin/gifts/withdrawals/'.$withdrawal->reference;
        $this->getJson($url)->assertOk()->assertJsonPath('data.withdrawal.payment_details.destination', 'Private destination');
        $this->postJson($url, ['status' => 'approved'])->assertOk();
        $this->postJson($url, ['status' => 'processing'])->assertOk();
        $this->assertSame(85, GiftEarningEntry::sole()->reserved_minor);
        $this->postJson($url, ['status' => 'failed'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'failed', 'reason' => 'Transfer returned'])->assertOk();
        $this->assertSame(85, GiftEarningEntry::sole()->reserved_minor);
        $this->postJson($url, ['status' => 'paid'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'paid', 'payout_reference' => 'BANK-123'])->assertOk();
        $this->postJson($url, ['status' => 'paid', 'payout_reference' => 'BANK-123'])->assertOk();
        $this->assertSame(85, GiftEarningEntry::sole()->settled_minor);
        $this->assertSame(0, GiftEarningEntry::sole()->reserved_minor);
        Sanctum::actingAs($this->recipient);
        $this->getJson('/api/gifts/earnings')->assertJsonPath('data.balances.0.total_withdrawn_minor', 85);
    }

    public function test_withdrawal_rejects_early_or_double_reservation_and_rejection_releases_once(): void
    {
        $this->paid();
        Sanctum::actingAs($this->recipient);
        $input = ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'request-once-after-hold', 'payment_details' => ['method' => 'manual', 'destination' => 'Private destination']];
        $this->postJson('/api/gifts/withdrawals', $input)->assertUnprocessable();
        $this->travel(25)->hours();
        app(GiftEarningsService::class)->release();
        $this->postJson('/api/gifts/withdrawals', $input)->assertOk();
        $this->postJson('/api/gifts/withdrawals', $input)->assertOk();
        $this->postJson('/api/gifts/withdrawals', array_replace($input, ['idempotency_key' => 'second-reservation-key']))->assertUnprocessable();
        $withdrawal = GiftWithdrawal::sole();
        $this->assertSame(85, GiftEarningEntry::sole()->reserved_minor);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $url = '/api/v1/admin/gifts/withdrawals/'.$withdrawal->reference;
        $this->postJson($url, ['status' => 'rejected'])->assertUnprocessable();
        $this->postJson($url, ['status' => 'rejected', 'reason' => 'Invalid destination'])->assertOk();
        $this->postJson($url, ['status' => 'rejected', 'reason' => 'Invalid destination'])->assertOk();
        $this->assertSame(85, GiftEarningEntry::sole()->available_minor);
        $this->assertSame(0, GiftEarningEntry::sole()->reserved_minor);
    }

    public function test_refund_before_release_reverses_entitlement_and_wallet_with_compensating_entries(): void
    {
        $gift = $this->paid();
        $earnings = app(GiftEarningsService::class);
        $earnings->reverse($gift, 100, 'wallet-refund-test');
        $earnings->reverse($gift, 100, 'wallet-refund-test');
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
        $this->assertSame(0, GiftEarningEntry::sole()->pending_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertSame(15, $gift->fresh()->reversed_commission_minor);
    }

    public function test_refund_after_external_payout_creates_recovery_and_blocks_new_withdrawals(): void
    {
        $gift = $this->paid();
        $this->travel(8)->days();
        $earnings = app(GiftEarningsService::class);
        $earnings->release();
        $withdrawal = $earnings->withdraw($this->recipient->id, ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'recovery-withdraw-key', 'payment_details' => ['method' => 'manual', 'destination' => 'example']]);
        $earnings->changeWithdrawal($withdrawal, 'approved', $this->sender->id, null);
        $earnings->changeWithdrawal($withdrawal, 'paid', $this->sender->id, 'PAYOUT-RECOVERY');
        $earnings->reverse($gift, 100, 'refund-after-payout');
        $this->assertSame(85, GiftEarningEntry::sole()->recovery_minor);
        $this->assertTrue($gift->fresh()->review_required);
        Sanctum::actingAs($this->recipient);
        $this->postJson('/api/gifts/withdrawals', ['amount_minor' => 1, 'currency' => 'USD', 'idempotency_key' => 'blocked-withdraw-key', 'payment_details' => ['method' => 'manual', 'destination' => 'example']])->assertUnprocessable();
    }

    public function test_admin_catalog_authorization_upload_validation_and_deactivation(): void
    {
        $this->getJson('/api/v1/admin/gifts/catalog')->assertUnauthorized();
        $historical = $this->paid();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Storage::fake('public');
        $data = ['name' => 'New Gift', 'price' => '2.50'];
        $response = $this->postJson('/api/v1/admin/gifts/catalog', $data + ['image' => UploadedFile::fake()->image('gift.png')])->assertOk()->assertJsonPath('data.price_minor', 250)->assertJsonPath('data.currency', 'USD')->assertJsonPath('data.is_active', true);
        $newGift = VirtualGift::findOrFail($response->json('data.id'));
        $this->assertMatchesRegularExpression('/^new-gift-[a-z0-9]{12}$/', $newGift->slug);
        Storage::disk('public')->assertExists($newGift->image_path);
        $this->assertSame(Storage::disk('public')->url($newGift->image_path), $newGift->image_url);
        $this->getJson('/api/virtual-gifts')->assertOk()->assertJsonFragment(['id' => $newGift->id]);
        $this->postJson('/api/v1/admin/gifts/catalog', $data)->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson('/api/v1/admin/gifts/catalog', ['name' => 'Invalid', 'price' => '-1', 'image' => UploadedFile::fake()->image('gift.png')])->assertUnprocessable();
        $this->postJson('/api/v1/admin/gifts/catalog', $data + ['image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml')])->assertUnprocessable();
        $this->postJson('/api/v1/admin/gifts/catalog/'.$newGift->id, ['name' => 'Updated Gift', 'price' => '3.25'])->assertOk();
        $this->assertSame(325, $newGift->fresh()->price_minor);
        $this->assertSame('Updated Gift', $newGift->fresh()->name);
        $this->postJson('/api/v1/admin/gifts/catalog/'.$this->gift->id, ['name' => 'Edited Coffee', 'price' => '4.00'])->assertOk();
        $this->assertSame('Coffee', $historical->fresh()->gift_snapshot['name']);
        $this->assertSame(100, $historical->fresh()->unit_price_minor);
        $this->deleteJson('/api/v1/admin/gifts/catalog/'.$newGift->id)->assertOk();
        $this->assertFalse($newGift->fresh()->is_active);
        $this->getJson('/api/virtual-gifts')->assertOk()->assertJsonMissing(['id' => $newGift->id]);
        $this->postJson('/api/v1/admin/gifts/catalog/'.$newGift->id.'/activate')->assertOk();
        $this->getJson('/api/virtual-gifts')->assertOk()->assertJsonFragment(['id' => $newGift->id]);
    }

    public function test_histories_and_status_do_not_expose_other_users_private_financial_data(): void
    {
        $gift = $this->paid();
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/gifts/'.$gift->reference.'/status')->assertNotFound();
        $this->getJson('/api/gifts/sent')->assertJsonCount(0, 'data.data');
        Sanctum::actingAs($this->recipient);
        $response = $this->getJson('/api/gifts/received')->assertOk()->assertJsonCount(1, 'data.data');
        $this->assertArrayNotHasKey('email', $response->json('data.data.0.sender'));
    }

    public function test_admin_wallet_refund_is_idempotent_and_cancels_reserved_withdrawals(): void
    {
        $gift = $this->paid();
        $this->travel(8)->days();
        $earnings = app(GiftEarningsService::class);
        $earnings->release();
        $withdrawal = $earnings->withdraw($this->recipient->id, ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'reserve-before-refund-key', 'payment_details' => ['method' => 'manual', 'destination' => 'test']]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->postJson('/api/v1/admin/gifts/transactions/'.$gift->reference.'/actions', ['action' => 'refund'])->assertOk()->assertJsonPath('data.payment_status', 'refunded');
        $this->postJson('/api/v1/admin/gifts/transactions/'.$gift->reference.'/actions', ['action' => 'refund'])->assertOk();
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
        $this->assertSame(0, GiftEarningEntry::sole()->reserved_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
    }

    public function test_partial_refunds_reverse_cumulative_commission_once_and_preserve_ledger_balance(): void
    {
        $gift = $this->paid();
        $service = app(GiftEarningsService::class);
        $service->reverse($gift, 10, 'partial-1');
        $this->assertSame(2, $gift->fresh()->reversed_commission_minor);
        $this->assertSame(8, $gift->fresh()->reversed_earning_minor);
        $service->reverse($gift, 20, 'partial-2');
        $service->reverse($gift, 20, 'partial-2');
        $this->assertSame(5, $gift->fresh()->reversed_commission_minor);
        $this->assertSame(25, $gift->fresh()->reversed_earning_minor);
        $this->assertSame(60, GiftEarningEntry::sole()->pending_minor);
        $service->reverse($gift, 70, 'partial-3');
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
    }

    public function test_capture_endpoint_uses_provider_evidence_and_enforces_sender_ownership(): void
    {
        $gift = $this->paypalGift();
        $resource = $this->captureResource($gift);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->with('ORDER-GIFT')->once()->andReturn(['id' => 'ORDER-GIFT', 'status' => 'COMPLETED', 'purchase_units' => [['custom_id' => $gift->payment->custom_id, 'payee' => ['merchant_id' => 'MERCHANT-TEST'], 'payments' => ['captures' => [$resource]]]]]);
        $gateway->shouldReceive('showCaptureDetails')->with('CAPTURE-GIFT')->once()->andReturn($resource);
        $this->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs($this->recipient);
        $this->postJson('/api/gifts/paypal/capture', ['reference' => $gift->reference])->assertNotFound();
        Sanctum::actingAs($this->sender);
        $this->postJson('/api/gifts/paypal/capture', ['reference' => $gift->reference])->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertDatabaseCount('gift_earning_entries', 1);
    }

    public function test_delayed_refund_before_completion_webhook_is_reconciled_atomically(): void
    {
        $gift = $this->paypalGift();
        $capture = $this->captureResource($gift, ['status' => 'REFUNDED']);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn(['id' => 'ORDER-GIFT', 'status' => 'COMPLETED', 'purchase_units' => [['custom_id' => $gift->payment->custom_id, 'payee' => ['merchant_id' => 'MERCHANT-TEST'], 'payments' => ['captures' => [$capture]]]]]);
        $gateway->shouldReceive('showCaptureDetails')->once()->andReturn($capture);
        $this->instance(PayPalGateway::class, $gateway);
        $this->webhook(['id' => 'WH-REFUND-FIRST', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['id' => 'REFUND-FIRST', 'status' => 'COMPLETED', 'amount' => ['value' => '1.00', 'currency_code' => 'USD'], 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-GIFT', 'capture_id' => 'CAPTURE-GIFT']]]])->assertOk();
        $this->assertSame('refunded', $gift->fresh()->payment_status);
        $this->assertSame('reversed', $gift->fresh()->delivery_status);
        $this->assertSame(0, GiftEarningEntry::sole()->pending_minor);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_verified_dispute_freezes_release_and_chargeback_reverses_earning(): void
    {
        $gift = $this->paypalGift();
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        $this->webhook(['id' => 'WH-DISPUTE', 'event_type' => 'CUSTOMER.DISPUTE.CREATED', 'resource' => ['dispute_id' => 'DISPUTE-1', 'disputed_transactions' => [['seller_transaction_id' => 'CAPTURE-GIFT']]]])->assertOk();
        $this->travel(8)->days();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(85, GiftEarningEntry::sole()->pending_minor);
        $this->webhook(['id' => 'WH-CHARGEBACK', 'event_type' => 'PAYMENT.CAPTURE.REVERSED', 'resource' => ['id' => 'CAPTURE-GIFT', 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-GIFT']]]])->assertOk();
        $this->assertSame('reversed', $gift->fresh()->payment_status);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
    }

    public function test_pending_and_cancelled_paypal_orders_never_deliver_on_browser_approval_alone(): void
    {
        $gift = $this->paypalGift();
        $this->get('/api/gifts/paypal/return?reference='.$gift->reference.'&token=ORDER-GIFT')->assertRedirect();
        $this->assertSame('pending', $gift->fresh()->delivery_status);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn(['id' => 'ORDER-GIFT', 'status' => 'APPROVED']);
        $gateway->shouldReceive('capturePaymentOrder')->with('ORDER-GIFT', 'gift-capture-'.$gift->reference)->once()->andReturn(['status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['status' => 'PENDING']]]]]]);
        $this->instance(PayPalGateway::class, $gateway);
        $this->postJson('/api/gifts/paypal/capture', ['reference' => $gift->reference])->assertOk()->assertJsonPath('data.delivery_status', 'pending');
        $this->postJson('/api/gifts/'.$gift->reference.'/cancel')->assertOk()->assertJsonPath('data.payment_status', 'cancelled');
        $this->assertDatabaseCount('gift_earning_entries', 0);
    }

    public function test_refund_gateway_uses_existing_sandbox_credentials_and_exact_decimal_strings(): void
    {
        config(['paypal.sandbox.client_id' => 'test-client', 'paypal.sandbox.client_secret' => 'test-secret']);
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'sandbox-test-token']), '*/refund' => Http::response(['id' => 'REFUND-TEST', 'status' => 'COMPLETED'])]);
        app(PayPalGateway::class)->refundCapture('CAPTURE-TEST', '0.10', 'USD', 'refund-idempotency-test');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/CAPTURE-TEST/refund') && $request['amount']['value'] === '0.10' && $request->hasHeader('PayPal-Request-Id', 'refund-idempotency-test'));
    }

    public function test_foreign_currency_provider_fees_keep_their_precision_and_do_not_get_summed_with_usd(): void
    {
        $gift = $this->paypalGift();
        $resource = $this->captureResource($gift, ['seller_receivable_breakdown' => ['paypal_fee' => ['value' => '60', 'currency_code' => 'JPY']]]);
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $resource);
        $this->assertSame(60, $gift->fresh()->payment_processing_fee_minor);
        $this->assertSame(0, (int) $gift->fresh()->processing_fee_precision);
        $this->assertSame('JPY', $gift->fresh()->processing_fee_currency);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/gifts/report')->assertOk()->assertJsonPath('data.financials.0.platform_net_minor', null)->assertJsonPath('data.processing_costs.0.currency', 'JPY');
    }

    public function test_wallet_only_currencies_do_not_create_unsupported_paypal_orders(): void
    {
        $this->gift->update(['currency' => 'EGP']);
        $this->postJson('/api/gifts/paypal/create-order', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(['wallet'], $this->gift->fresh()->payment_methods);
    }

    public function test_invalid_ledger_write_rolls_back_debit_and_gift_delivery_together(): void
    {
        GiftEarningEntry::creating(function () {
            throw new \RuntimeException('Simulated earning failure');
        });
        try {
            $this->postJson('/api/gifts/send/wallet', $this->input())->assertStatus(502);
        } finally {
            GiftEarningEntry::flushEventListeners();
        }
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('gift_transactions', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_direct_paypal_refund_and_webhook_retry_reverse_once_without_refunding_the_spending_wallet(): void
    {
        $gift = $this->paypalGift();
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('refundCapture')->once()->with('CAPTURE-GIFT', '1.00', 'USD', 'gift-refund-'.$gift->reference)->andReturn(['id' => 'REFUND-VERIFIED', 'status' => 'COMPLETED', 'amount' => ['value' => '1.00', 'currency_code' => 'USD']]);
        $this->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $path = '/api/v1/admin/gifts/transactions/'.$gift->reference.'/actions';
        $this->postJson($path, ['action' => 'refund'])->assertOk()->assertJsonPath('data.payment_status', 'refunded');
        $this->postJson($path, ['action' => 'refund'])->assertOk();
        $this->webhook(['id' => 'WH-REFUND-RETRY', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['id' => 'REFUND-VERIFIED', 'status' => 'COMPLETED', 'amount' => ['value' => '1.00', 'currency_code' => 'USD'], 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-GIFT', 'capture_id' => 'CAPTURE-GIFT']]]])->assertOk();
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(85, GiftEarningEntry::sole()->reversed_minor);
        $this->assertSame(60, $gift->fresh()->payment_processing_fee_minor);
        $this->assertDatabaseCount('gift_earning_movements', 2);
    }

    public function test_missing_provider_fee_can_be_confirmed_later_without_duplicate_expense_or_earning(): void
    {
        $gift = $this->paypalGift();
        $resource = $this->captureResource($gift);
        unset($resource['seller_receivable_breakdown']);
        $finalizer = app(PaymentFinalizer::class);
        $finalizer->finalizeVirtualGift($gift->payment, $resource);
        $this->assertNull($gift->fresh()->payment_processing_fee_minor);
        $finalizer->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        $finalizer->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        $this->assertSame(60, $gift->fresh()->payment_processing_fee_minor);
        $this->assertDatabaseCount('gift_earning_entries', 1);
        $this->assertSame(1, DB::table('gift_transaction_events')->where('type', 'processing_fee')->count());
    }

    public function test_missed_partial_refund_can_be_verified_from_its_provider_id_without_overwriting_history(): void
    {
        $gift = $this->paypalGift();
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showRefundDetails')->twice()->with('REFUND-PARTIAL')->andReturn(['id' => 'REFUND-PARTIAL', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '0.30'], 'links' => [['rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURE-GIFT']]]);
        $this->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $path = '/api/v1/admin/gifts/transactions/'.$gift->reference.'/actions';
        $this->postJson($path, ['action' => 'reconcile_refund', 'proof_reference' => 'REFUND-PARTIAL'])->assertOk();
        $this->postJson($path, ['action' => 'reconcile_refund', 'proof_reference' => 'REFUND-PARTIAL'])->assertOk();
        $this->assertSame(30, $gift->fresh()->refunded_gross_minor);
        $this->assertSame(5, $gift->fresh()->reversed_commission_minor);
        $this->assertSame(60, GiftEarningEntry::sole()->pending_minor);
        $this->assertDatabaseCount('gift_earning_movements', 2);
    }

    public function test_reconciliation_freezes_a_new_partial_refund_revision_even_when_an_earlier_refund_is_known(): void
    {
        $gift = $this->paypalGift();
        app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $this->captureResource($gift));
        app(GiftEarningsService::class)->reverse($gift, 10, 'known-earlier-refund');
        $capture = $this->captureResource($gift, ['status' => 'PARTIALLY_REFUNDED', 'update_time' => '2026-10-20T12:00:00Z']);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->andReturn(['id' => 'ORDER-GIFT', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [$capture]]]]]);
        $gateway->shouldReceive('showCaptureDetails')->once()->andReturn($capture);
        $this->instance(PayPalGateway::class, $gateway);
        app(PayPalServices::class)->reconcileGift($gift->payment);
        $this->assertTrue($gift->fresh()->review_required);
        $this->assertNotNull($gift->fresh()->partial_refund_marker);
        $this->travel(8)->days();
        $this->artisan('gifts:release-earnings')->assertSuccessful();
        $this->assertSame(77, GiftEarningEntry::sole()->pending_minor);
    }

    public function test_unsigned_gift_webhook_cannot_deliver_or_create_financial_entitlements(): void
    {
        $gift = $this->paypalGift();
        $this->instance(PayPalWebhookVerifier::class, Mockery::mock(PayPalWebhookVerifier::class)->shouldReceive('verify')->andReturn(false)->getMock());
        $this->postJson('/api/v1/paypal/webhook', ['id' => 'UNSIGNED-GIFT', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => $this->captureResource($gift)])->assertStatus(400);
        $this->assertDatabaseCount('gift_earning_entries', 0);
        $this->assertSame('pending', $gift->fresh()->delivery_status);
    }

    public function test_pausing_new_gifts_preserves_existing_payment_retries_and_administration(): void
    {
        $gift = $this->paid();
        config(['virtual_gifts.enabled' => false]);
        $this->getJson('/api/virtual-gifts')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertOk()->assertJsonPath('data.reference', $gift->reference);
        $this->postJson('/api/gifts/send/wallet', $this->input(['idempotency_key' => 'new-disabled-gift-request']))->assertStatus(503);
        $this->assertSame(9900, $this->sender->wallet->fresh()->balance_minor);
        $this->assertDatabaseCount('gift_transactions', 1);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/gifts/catalog')->assertOk();
    }

    public function test_stale_catalog_price_cannot_silently_charge_a_different_amount(): void
    {
        $this->gift->update(['price_minor' => 200]);
        $this->postJson('/api/gifts/send/wallet', $this->input(['expected_unit_price_minor' => 100, 'expected_currency' => 'USD']))->assertConflict();
        $this->assertDatabaseCount('gift_transactions', 0);
        $this->assertSame(10000, $this->sender->wallet->fresh()->balance_minor);
    }

    public function test_paid_gift_payment_history_cannot_be_deleted_through_legacy_cascades(): void
    {
        $gift = $this->paid();
        try {
            $gift->payment->delete();
            $this->fail('Expected retained financial references to block deletion.');
        } catch (QueryException) {
            $this->assertDatabaseHas('payments', ['id' => $gift->payment_id]);
            $this->assertDatabaseHas('gift_transactions', ['id' => $gift->id]);
            $this->assertDatabaseHas('gift_earning_entries', ['gift_transaction_id' => $gift->id]);
        }
    }

    public function test_legacy_wallet_denomination_is_preserved_without_silently_fixing_conflicting_balances(): void
    {
        $this->sender->wallet->update(['balance_minor' => null, 'amount' => '100.00']);
        $this->getJson('/api/gifts/earnings')->assertOk()->assertJsonPath('data.wallet.balance_minor', 10000)->assertJsonPath('data.wallet.is_consistent', true);
        $this->sender->wallet->update(['balance_minor' => 10000, 'amount' => '90.00']);
        $this->getJson('/api/gifts/earnings')->assertOk()->assertJsonPath('data.wallet.is_consistent', false);
        $this->postJson('/api/gifts/send/wallet', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}

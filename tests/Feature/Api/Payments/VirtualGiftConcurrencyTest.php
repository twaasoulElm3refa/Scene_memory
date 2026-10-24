<?php

namespace Tests\Feature\Api\Payments;

use App\Exceptions\CommerceException;
use App\Http\Controllers\api\admin\VirtualGiftAdminController;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\GiftEarningEntry;
use App\Models\GiftTransaction;
use App\Models\GiftWithdrawal;
use App\Models\User;
use App\Models\VirtualGift;
use App\Models\Wallet;
use App\Services\CheckoutCartSnapshot;
use App\Services\GiftEarningsService;
use App\Services\MinorMoney;
use App\Services\PaymentFinalizer;
use App\Services\PayPalGateway;
use App\Services\VirtualGiftService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/** Opt-in, committed fixtures in a disposable database; no database resets. */
class VirtualGiftConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('GIFTS_MYSQL_CONCURRENCY')) {
            $this->markTestSkipped('Use tests/run-gifts-mysql-concurrency.sh.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringStartsWith('scemory_gifts_test', config('database.connections.mysql.database'));
        config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'cache.default' => 'array']);
        Http::preventStrayRequests();
        $this->instance(PayPalGateway::class, Mockery::mock(PayPalGateway::class));
    }

    private function workers(int $count, callable $work): void
    {
        DB::disconnect();
        $children = [];
        for ($index = 0; $index < $count; $index++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $work($index);
                    exit(0);
                } catch (\Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage().PHP_EOL);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
    }

    private function fixture(int $balance): array
    {
        $sender = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $recipient = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $event = Events::create(['title' => 'Concurrent gifts', 'user_id' => $recipient->id, 'is_active' => 1]);
        $gift = VirtualGift::create(['slug' => 'concurrency-'.uniqid(), 'name' => 'Concurrency gift', 'currency' => 'USD', 'price_minor' => 100]);
        Wallet::create(['user_id' => $sender->id, 'currency' => 'USD', 'balance_minor' => $balance, 'amount' => app(MinorMoney::class)->toDecimal($balance)]);

        return [$sender, $recipient, ['gift_id' => $gift->id, 'event_id' => $event->id, 'quantity' => 1]];
    }

    public function test_concurrent_media_finalization_grants_and_debits_once(): void
    {
        Bus::fake();
        [$buyer, $seller, $input] = $this->fixture(1000);
        $media = EventsImges::create(['event_id' => $input['event_id'], 'type' => 'image', 'price' => '1.00', 'is_active' => 1]);
        $snapshot = app(CheckoutCartSnapshot::class)->create(['user_id' => $buyer->id, 'type' => 'single_media', 'media_id' => $media->id, 'idempotency_key' => 'media-concurrency-'.uniqid()], 'wallet');
        $this->workers(6, fn () => app(PaymentFinalizer::class)->finalizeWalletPurchase($snapshot['payment']));
        $this->assertSame(900, $buyer->wallet->fresh()->balance_minor);
        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->where('media_id', $media->id)->count());
        $this->assertSame(1, $buyer->wallet->walletTransactions()->count());
        $this->assertSame('completed', $snapshot['payment']->fresh()->status);
    }

    public function test_competing_media_orders_cannot_overspend_a_wallet(): void
    {
        Bus::fake();
        [$buyer, $seller, $input] = $this->fixture(100);
        $payments = [];
        for ($i = 0; $i < 6; $i++) {
            $media = EventsImges::create(['event_id' => $input['event_id'], 'type' => 'image', 'price' => '1.00', 'is_active' => 1]);
            $payments[] = app(CheckoutCartSnapshot::class)->create(['user_id' => $buyer->id, 'type' => 'single_media', 'media_id' => $media->id, 'idempotency_key' => 'media-competing-'.uniqid()], 'wallet')['payment'];
        }
        $this->workers(6, function ($index) use ($payments) {
            try {
                app(PaymentFinalizer::class)->finalizeWalletPurchase($payments[$index]);
            } catch (CommerceException|\RuntimeException $exception) {
                if ($exception->getMessage() !== 'Insufficient wallet balance.') {
                    throw $exception;
                }
            }
        });
        $this->assertSame(0, $buyer->wallet->fresh()->balance_minor);
        $this->assertSame(1, Entitlement::where('user_id', $buyer->id)->count());
        $this->assertSame(1, $buyer->wallet->walletTransactions()->count());
    }

    public function test_duplicate_wallet_purchases_deliver_and_debit_exactly_once(): void
    {
        [$sender, $recipient, $input] = $this->fixture(1000);
        $this->workers(8, fn () => app(VirtualGiftService::class)->sendWallet($sender->id, $input + ['idempotency_key' => 'same-concurrent-gift-key']));
        $this->assertSame(900, $sender->wallet->fresh()->balance_minor);
        $this->assertSame(1, GiftTransaction::where('sender_user_id', $sender->id)->count());
        $this->assertSame(85, (int) GiftEarningEntry::where('recipient_user_id', $recipient->id)->sum('pending_minor'));
        $this->assertSame(1, $sender->wallet->walletTransactions()->count());
    }

    public function test_competing_purchases_never_overspend_available_balance(): void
    {
        [$sender, $recipient, $input] = $this->fixture(100);
        $this->workers(6, function (int $index) use ($sender, $input) {
            try {
                app(VirtualGiftService::class)->sendWallet($sender->id, $input + ['idempotency_key' => 'distinct-concurrent-key-'.$index]);
            } catch (CommerceException $exception) {
                if ($exception->getMessage() !== 'Insufficient wallet balance.') {
                    throw $exception;
                }
            }
        });
        $this->assertSame(0, $sender->wallet->fresh()->balance_minor);
        $this->assertSame(1, GiftTransaction::where('sender_user_id', $sender->id)->count());
        $this->assertSame(1, GiftEarningEntry::where('recipient_user_id', $recipient->id)->count());
    }

    public function test_release_and_refund_retries_cannot_duplicate_creator_funds_or_wallet_credit(): void
    {
        [$sender, $recipient, $input] = $this->fixture(1000);
        $gift = app(VirtualGiftService::class)->sendWallet($sender->id, $input + ['idempotency_key' => 'release-refund-concurrent']);
        $gift->earning->update(['release_at' => now()->subDay()]);
        $this->workers(6, function (int $index) use ($gift) {
            if ($index < 3) {
                app(GiftEarningsService::class)->release();
            } else {
                app(GiftEarningsService::class)->reverse($gift, 100, 'same-concurrent-refund');
            }
        });
        $entry = $gift->earning->fresh();
        $this->assertSame(0, $entry->pending_minor + $entry->available_minor + $entry->reserved_minor);
        $this->assertSame(85, $entry->reversed_minor);
        $this->assertSame(1000, $sender->wallet->fresh()->balance_minor);
        $this->assertSame(2, $sender->wallet->walletTransactions()->count());
    }

    public function test_concurrent_verified_capture_callbacks_create_one_delivery_and_one_earning(): void
    {
        [$sender, $recipient, $input] = $this->fixture(1000);
        $gift = app(VirtualGiftService::class)->create($sender->id, $input + ['idempotency_key' => 'paypal-concurrent-capture'], 'paypal');
        $orderId = 'ORDER-'.$gift->id;
        $gift->update(['paypal_order_id' => $orderId]);
        $gift->payment->update(['paypal_order_id' => $orderId]);
        config(['paypal.merchant_id' => 'TEST-MERCHANT']);
        $resource = ['id' => 'CAPTURE-'.$gift->id, 'status' => 'COMPLETED', 'custom_id' => $gift->payment->custom_id, 'amount' => ['value' => '1.00', 'currency_code' => 'USD'], 'payee' => ['merchant_id' => 'TEST-MERCHANT'], 'supplementary_data' => ['related_ids' => ['order_id' => $orderId]]];
        $this->workers(6, fn () => app(PaymentFinalizer::class)->finalizeVirtualGift($gift->payment, $resource));
        $this->assertSame(1, GiftEarningEntry::where('gift_transaction_id', $gift->id)->count());
        $this->assertSame(85, $gift->earning->fresh()->pending_minor);
        $this->assertSame('delivered', $gift->fresh()->delivery_status);
        $this->assertSame(1000, $sender->wallet->fresh()->balance_minor);
    }

    public function test_concurrent_withdrawals_cannot_reserve_the_same_earnings_twice(): void
    {
        [$sender, $recipient, $input] = $this->fixture(1000);
        $gift = app(VirtualGiftService::class)->sendWallet($sender->id, $input + ['idempotency_key' => 'withdrawal-concurrent-gift']);
        $gift->update(['paid_at' => now()->subDays(2)]);
        $gift->earning->update(['release_at' => now()->subDay()]);
        app(GiftEarningsService::class)->release();
        $this->workers(6, function (int $index) use ($recipient) {
            try {
                app(GiftEarningsService::class)->withdraw($recipient->id, ['amount_minor' => 85, 'currency' => 'USD', 'idempotency_key' => 'concurrent-withdrawal-'.$index, 'payment_details' => ['method' => 'test', 'destination' => 'test-destination']]);
            } catch (CommerceException $exception) {
                if ($exception->getMessage() !== 'Insufficient available earnings.') {
                    throw $exception;
                }
            }
        });
        $this->assertSame(85, $gift->earning->fresh()->reserved_minor);
        $this->assertSame(0, $gift->earning->fresh()->available_minor);
        $this->assertSame(1, GiftWithdrawal::where('user_id', $recipient->id)->count());
        $report = app(VirtualGiftAdminController::class)->report();
        $this->assertSame(200, $report->getStatusCode());
        $this->assertNotEmpty($report->getData(true)['data']['financials']);
    }
}

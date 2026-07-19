<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Cart;
use App\Models\CartItems;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Services\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayPalPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        config()->set('app.frontend_url', 'http://localhost:5173');
        config()->set('paypal.currency', 'USD');
    }

    public function test_cart_checkout_ignores_client_amount_and_snapshots_server_prices(): void
    {
        [$buyer, $event, $images] = $this->catalog([4250]);
        $cart = Cart::create(['user_id' => $buyer->id]);
        $cartItem = CartItems::create([
            'cart_id' => $cart->id,
            'image_id' => $images[0]->id,
            'event_id' => $event->id,
            'type' => 'single',
            'price' => '0.50',
        ]);
        $this->mockCreateOrder('42.50', 'PAYPAL-CREATED-1');
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', [
            'type' => 'cart',
            'amount' => '0.01',
            'idempotency_key' => 'browser-attempt-1',
        ])->assertOk()->assertJson([
            'approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-CREATED-1',
        ]);

        $order = Purchases::findOrFail($response->json('order_id'));
        $this->assertSame(4250, $order->amount_minor);
        $this->assertSame('cart', $order->order_type);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $order->id,
            'image_id' => $images[0]->id,
            'source_cart_item_id' => $cartItem->id,
            'price' => 42.50,
        ]);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'paypal', 'status' => 'pending']);
    }

    #[DataProvider('directPurchaseProvider')]
    public function test_direct_paypal_purchase_types_are_server_priced(string $type, int $expectedMinor): void
    {
        [$buyer, $event, $images] = $this->catalog([1000, 2000, 3000]);
        $payload = match ($type) {
            'single_media' => ['media_id' => $images[0]->id],
            'collection' => ['collection_id' => $event->id],
            'multiple_media' => ['media_ids' => [$images[0]->id, $images[1]->id, $images[0]->id]],
        };
        $this->mockCreateOrder(number_format($expectedMinor / 100, 2, '.', ''), 'PAYPAL-'.$type);
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', array_merge($payload, [
            'type' => $type,
            'amount' => '0.01',
            'idempotency_key' => 'key-'.$type,
        ]))->assertOk();

        $order = Purchases::findOrFail($response->json('order_id'));
        $this->assertSame($type, $order->order_type);
        $this->assertSame($expectedMinor, $order->amount_minor);
        $this->assertSame($type === 'single_media' ? 1 : ($type === 'collection' ? 3 : 2), $order->items()->count());
    }

    public static function directPurchaseProvider(): array
    {
        return [
            'single media' => ['single_media', 1000],
            'collection grants all media with 10 percent discount' => ['collection', 5400],
            'multiple media removes duplicate IDs' => ['multiple_media', 3000],
        ];
    }

    public function test_repeated_pay_request_reuses_same_local_and_paypal_order(): void
    {
        [$buyer, , $images] = $this->catalog([1200]);
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn($this->createdOrder('PAYPAL-IDEMPOTENT'));
        $gateway->shouldReceive('showOrderDetails')->once()->with('PAYPAL-IDEMPOTENT')->andReturn($this->createdOrder('PAYPAL-IDEMPOTENT'));
        $this->app->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs($buyer);
        $payload = ['type' => 'single_media', 'media_id' => $images[0]->id, 'idempotency_key' => 'same-browser-key'];

        $first = $this->postJson('/api/v1/pay', $payload)->assertOk()->json('order_id');
        $second = $this->postJson('/api/v1/pay', $payload)->assertOk()->json('order_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Purchases::count());
    }

    public function test_success_callback_does_not_complete_or_grant_ownership(): void
    {
        [$buyer, , $images] = $this->catalog([900]);
        $order = Purchases::create([
            'user_id' => $buyer->id, 'payment_method' => 'paypal', 'status' => 'pending', 'currency' => 'USD',
            'amount' => '9.00', 'amount_minor' => 900, 'paypal_order_id' => 'PAYPAL-CALLBACK',
            'idempotency_key' => 'callback-order', 'type' => 'checkout', 'order_type' => 'single_media',
        ]);
        $order->items()->create(['image_id' => $images[0]->id, 'price' => '9.00']);
        Payment::create([
            'order_id' => $order->id, 'user_id' => $buyer->id, 'operation' => 'purchase', 'method' => 'paypal',
            'status' => 'pending', 'amount_minor' => 900, 'currency' => 'USD', 'idempotency_key' => 'callback-payment',
            'paypal_order_id' => 'PAYPAL-CALLBACK',
        ]);

        $this->get('/api/v1/paypal/success?token=PAYPAL-CALLBACK')
            ->assertRedirect('http://localhost:5173/en/waiting?order_id='.$order->id);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseMissing('entitlements', ['user_id' => $buyer->id, 'media_id' => $images[0]->id]);
    }

    public function test_wallet_deposit_creates_a_separate_server_validated_payment(): void
    {
        $user = User::factory()->create();
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->withArgs(function (array $request, string $requestId) {
            return data_get($request, 'purchase_units.0.amount.value') === '25.50'
                && str_starts_with(data_get($request, 'purchase_units.0.custom_id'), 'wallet_topup:')
                && str_starts_with($requestId, 'create-wallet-deposit-');
        })->andReturn($this->createdOrder('PAYPAL-DEPOSIT'));
        $this->app->instance(PayPalGateway::class, $gateway);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/deposit/pay', [
            'amount' => '25.50', 'idempotency_key' => 'deposit-browser-key',
        ])->assertOk()->assertJson(['approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-DEPOSIT']);

        $payment = Payment::where('order_id', $response->json('order_id'))->sole();
        $this->assertSame('wallet_deposit', $payment->operation);
        $this->assertSame(2550, $payment->amount_minor);
        $this->assertFalse($payment->wallet_credited);
    }

    private function catalog(array $pricesMinor): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);
        $images = collect($pricesMinor)->map(fn (int $minor) => EventsImges::create([
            'event_id' => $event->id,
            'is_active' => '1',
            'price' => number_format($minor / 100, 2, '.', ''),
            'type' => 'image',
        ]));

        return [$buyer, $event, $images];
    }

    private function mockCreateOrder(string $amount, string $paypalId): void
    {
        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->withArgs(function (array $request, string $requestId) use ($amount) {
            return data_get($request, 'purchase_units.0.amount.value') === $amount
                && str_starts_with(data_get($request, 'purchase_units.0.custom_id'), 'purchase:')
                && str_starts_with($requestId, 'create-purchase-');
        })->andReturn($this->createdOrder($paypalId));
        $this->app->instance(PayPalGateway::class, $gateway);
    }

    private function createdOrder(string $id): array
    {
        return [
            'id' => $id,
            'status' => 'CREATED',
            'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token='.$id]],
        ];
    }
}

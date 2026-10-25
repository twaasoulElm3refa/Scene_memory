<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Cart;
use App\Models\CartItems;
use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PaymentFinalizer;
use App\Services\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DirectBuyNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config()->set('app.url', 'http://localhost');
        config()->set('app.frontend_url', 'http://localhost:5173');
        config()->set('paypal.currency', 'USD');
        config()->set('paypal.merchant_id', 'MERCHANT-BUY-NOW');
    }

    public function test_unauthenticated_user_cannot_initiate_direct_buy_now(): void
    {
        $response = $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => 999,
            'idempotency_key' => 'unauth-key',
        ]);

        $response->assertUnauthorized();
    }

    public function test_direct_buy_now_creates_single_media_order_and_leaves_existing_cart_untouched(): void
    {
        [$buyer, $seller, $event, $cartImages, $buyNowImage] = $this->setupCatalogWithCart([1000, 2000, 3000], 1500);

        $this->mockPayPalCreateOrder('15.00', 'PAYPAL-BUYNOW-1');
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'buy-now-key-1',
        ])->assertOk()->assertJson([
            'approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-BUYNOW-1',
        ]);

        $orderId = $response->json('order_id');
        $this->assertNotNull($orderId);

        $order = Purchases::findOrFail($orderId);
        $this->assertSame('single_media', $order->order_type);
        $this->assertSame(1500, $order->amount_minor);
        $this->assertSame('pending', $order->status);

        // Assert purchase_items has 1 item pointing to $buyNowImage with null source_cart_item_id
        $this->assertSame(1, $order->items()->count());
        $item = $order->items()->first();
        $this->assertSame($buyNowImage->id, $item->image_id);
        $this->assertNull($item->source_cart_item_id);

        // CRITICAL ASSERTION: The buyer's cart still has all 3 items untouched!
        $buyerCart = Cart::where('user_id', $buyer->id)->first();
        $this->assertNotNull($buyerCart);
        $this->assertSame(3, CartItems::where('cart_id', $buyerCart->id)->count());
        foreach ($cartImages as $img) {
            $this->assertDatabaseHas('cart_items', [
                'cart_id' => $buyerCart->id,
                'image_id' => $img->id,
            ]);
        }
    }

    public function test_direct_buy_now_authoritative_server_pricing_ignores_client_tampering(): void
    {
        [$buyer, , , , $buyNowImage] = $this->setupCatalogWithCart([], 2500); // $25.00 in DB

        // Client attempts to pay $0.01 in EUR
        $this->mockPayPalCreateOrder('25.00', 'PAYPAL-BUYNOW-TAMPER');
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'amount' => '0.01',
            'currency' => 'EUR',
            'idempotency_key' => 'tamper-attempt-key',
        ])->assertOk();

        $order = Purchases::findOrFail($response->json('order_id'));
        $this->assertSame(2500, $order->amount_minor);
        $this->assertSame('USD', $order->currency);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount_minor' => 2500,
            'currency' => 'USD',
        ]);
    }

    public function test_direct_buy_now_fails_when_media_is_inactive(): void
    {
        [$buyer, , , , $buyNowImage] = $this->setupCatalogWithCart([], 1500);
        $buyNowImage->update(['is_active' => '0']);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'inactive-media-key',
        ])->assertStatus(422);
    }

    public function test_direct_buy_now_fails_when_event_is_inactive(): void
    {
        [$buyer, , $event, , $buyNowImage] = $this->setupCatalogWithCart([], 1500);
        $event->update(['is_active' => false]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'inactive-event-key',
        ])->assertStatus(422);
    }

    public function test_direct_buy_now_fails_when_buyer_already_owns_media(): void
    {
        [$buyer, , , , $buyNowImage] = $this->setupCatalogWithCart([], 1500);

        Entitlement::create([
            'user_id' => $buyer->id,
            'media_id' => $buyNowImage->id,
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'already-owned-key',
        ])->assertStatus(409)->assertJson([
            'message' => 'One or more media items are already owned.',
        ]);
    }

    public function test_direct_buy_now_settlement_grants_entitlement_credits_seller_and_preserves_cart(): void
    {
        [$buyer, $seller, , $cartImages, $buyNowImage] = $this->setupCatalogWithCart([1000, 2000], 1500);

        $this->mockPayPalCreateOrder('15.00', 'PAYPAL-BUYNOW-SETTLE');
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'settle-key-1',
        ])->assertOk();

        $order = Purchases::findOrFail($response->json('order_id'));
        $payment = Payment::where('order_id', $order->id)->sole();

        $resource = $this->captureResource($payment);

        $finalizer = app(PaymentFinalizer::class);
        $completedOrder = $finalizer->finalizeCompletedPurchaseCapture($payment, $resource);

        $this->assertSame('completed', $completedOrder->status);
        $this->assertSame('completed', $payment->fresh()->status);

        // 1. Entitlement granted for buyer and Buy Now image
        $this->assertDatabaseHas('entitlements', [
            'user_id' => $buyer->id,
            'media_id' => $buyNowImage->id,
            'order_id' => $order->id,
        ]);

        // 2. Seller wallet credited 85% creator earnings (1500 * 0.85 = 1275 minor units)
        $sellerWallet = Wallet::where('user_id', $seller->id)->first();
        $this->assertNotNull($sellerWallet);
        $this->assertSame(1275, (int) $sellerWallet->balance_minor);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $sellerWallet->id,
            'amount_minor' => 1275,
            'type' => 'credit',
            'source' => 'media_sale',
        ]);

        // 3. Buyer's existing cart items are completely intact!
        $buyerCart = Cart::where('user_id', $buyer->id)->first();
        $this->assertSame(2, CartItems::where('cart_id', $buyerCart->id)->count());
        foreach ($cartImages as $img) {
            $this->assertDatabaseHas('cart_items', [
                'cart_id' => $buyerCart->id,
                'image_id' => $img->id,
            ]);
        }
    }

    public function test_direct_buy_now_settlement_is_strictly_idempotent(): void
    {
        [$buyer, $seller, , , $buyNowImage] = $this->setupCatalogWithCart([], 1500);

        $this->mockPayPalCreateOrder('15.00', 'PAYPAL-BUYNOW-IDEMP');
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay', [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'idemp-key-1',
        ])->assertOk();

        $order = Purchases::findOrFail($response->json('order_id'));
        $payment = Payment::where('order_id', $order->id)->sole();
        $resource = $this->captureResource($payment);

        $finalizer = app(PaymentFinalizer::class);

        // Call once
        $finalizer->finalizeCompletedPurchaseCapture($payment, $resource);

        // Call second time
        $finalizer->finalizeCompletedPurchaseCapture($payment, $resource);

        // Assert strictly 1 entitlement, 1 transaction
        $this->assertDatabaseCount('entitlements', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);

        $sellerWallet = Wallet::where('user_id', $seller->id)->first();
        $this->assertSame(1275, (int) $sellerWallet->balance_minor);
    }

    public function test_repeated_pay_request_with_same_idempotency_key_reuses_same_order(): void
    {
        [$buyer, , , , $buyNowImage] = $this->setupCatalogWithCart([], 1500);

        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')->once()->andReturn($this->createdOrder('PAYPAL-REUSE-1'));
        $gateway->shouldReceive('showOrderDetails')->once()->with('PAYPAL-REUSE-1')->andReturn($this->createdOrder('PAYPAL-REUSE-1'));
        $this->app->instance(PayPalGateway::class, $gateway);

        Sanctum::actingAs($buyer);

        $payload = [
            'type' => 'single_media',
            'media_id' => $buyNowImage->id,
            'idempotency_key' => 'browser-repeat-key',
        ];

        $first = $this->postJson('/api/v1/pay', $payload)->assertOk()->json('order_id');
        $second = $this->postJson('/api/v1/pay', $payload)->assertOk()->json('order_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Purchases::count());
        $this->assertSame(1, Payment::count());
    }

    private function setupCatalogWithCart(array $cartPricesMinor, int $buyNowPriceMinor): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);

        // Cart items for buyer
        $cart = Cart::create(['user_id' => $buyer->id]);
        $cartImages = collect($cartPricesMinor)->map(function (int $minor) use ($event, $cart) {
            $image = EventsImges::create([
                'event_id' => $event->id,
                'is_active' => '1',
                'price' => number_format($minor / 100, 2, '.', ''),
                'type' => 'image',
            ]);

            CartItems::create([
                'cart_id' => $cart->id,
                'image_id' => $image->id,
                'event_id' => $event->id,
                'type' => 'single',
                'price' => $image->price,
            ]);

            return $image;
        });

        // Standalone image for Buy Now direct checkout
        $buyNowImage = EventsImges::create([
            'event_id' => $event->id,
            'is_active' => '1',
            'price' => number_format($buyNowPriceMinor / 100, 2, '.', ''),
            'type' => 'image',
        ]);

        return [$buyer, $seller, $event, $cartImages, $buyNowImage];
    }

    private function mockPayPalCreateOrder(string $amount, string $paypalId): void
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

    private function captureResource(Payment $payment): array
    {
        return [
            'id' => 'CAPTURE-'.$payment->id,
            'status' => 'COMPLETED',
            'amount' => [
                'value' => number_format($payment->amount_minor / 100, 2, '.', ''),
                'currency_code' => 'USD',
            ],
            'custom_id' => $payment->custom_id,
            'reference_id' => $payment->reference_id,
            'payee' => [
                'merchant_id' => 'MERCHANT-BUY-NOW',
            ],
            'supplementary_data' => [
                'related_ids' => [
                    'order_id' => $payment->paypal_order_id,
                ],
            ],
        ];
    }
}

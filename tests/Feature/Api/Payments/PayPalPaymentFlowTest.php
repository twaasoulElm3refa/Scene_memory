<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Cart;
use App\Models\CartItems;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Models\User;
use App\Services\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
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

    public function test_checkout_ignores_client_amount_and_snapshots_database_prices_before_paypal(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id]);
        $image = EventsImges::create(['event_id' => $event->id, 'price' => 42.50]);
        $cart = Cart::create(['user_id' => $buyer->id]);
        $cartItem = CartItems::create([
            'cart_id' => $cart->id,
            'image_id' => $image->id,
            'type' => 'single',
            'price' => 0.50,
        ]);

        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('createOrder')
            ->once()
            ->withArgs(function (array $request) {
                return data_get($request, 'purchase_units.0.amount.value') === '42.50'
                    && data_get($request, 'application_context.return_url') === 'http://localhost/api/v1/paypal/success'
                    && data_get($request, 'application_context.cancel_url') === 'http://localhost/api/v1/paypal/cancel';
            })
            ->andReturn([
                'id' => 'PAYPAL-CREATED-1',
                'status' => 'CREATED',
                'links' => [[
                    'rel' => 'approve',
                    'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-CREATED-1',
                ]],
            ]);
        $this->app->instance(PayPalGateway::class, $gateway);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/pay', [
            'amount' => 0.01,
            'idempotency_key' => 'browser-attempt-1',
        ])->assertOk()->assertJson([
            'success' => true,
            'approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-CREATED-1',
        ]);

        $purchase = Purchases::query()->sole();
        $this->assertSame('42.50', number_format((float) $purchase->amount, 2, '.', ''));
        $this->assertSame('pending', $purchase->status);
        $this->assertSame('PAYPAL-CREATED-1', $purchase->paypal_order_id);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'image_id' => $image->id,
            'source_cart_item_id' => $cartItem->id,
            'price' => 42.50,
        ]);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);
    }

    public function test_success_callback_only_reads_status_and_redirects_to_frontend(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        $event = Events::create(['user_id' => User::factory()->create()->id]);
        $image = EventsImges::create(['event_id' => $event->id, 'price' => 9]);
        $cartItem = CartItems::create([
            'cart_id' => $cart->id,
            'image_id' => $image->id,
            'price' => 9,
        ]);
        $purchase = Purchases::create([
            'user_id' => $user->id,
            'payment_method' => 'paypal',
            'status' => 'pending',
            'currency' => 'USD',
            'amount' => 9,
            'paypal_order_id' => 'PAYPAL-CALLBACK-1',
            'description' => 'Snapshot exists',
            'idempotency_key' => 'callback-test',
            'type' => 'checkout',
        ]);
        $snapshot = PurchaseItems::create([
            'purchase_id' => $purchase->id,
            'image_id' => $image->id,
            'source_cart_item_id' => $cartItem->id,
            'price' => 9,
        ]);

        $this->get('/api/v1/paypal/success?token=PAYPAL-CALLBACK-1')
            ->assertRedirect('http://localhost:5173/en/waiting?order_id='.$purchase->id.'&status=pending');

        $this->assertSame('pending', $purchase->fresh()->status);
        $this->assertDatabaseHas('purchase_items', ['id' => $snapshot->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id]);
    }
}

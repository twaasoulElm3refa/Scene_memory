<?php

namespace Tests\Feature\Api\Payments;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Payment;
use App\Models\Purchases;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PayPalReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_approved_payment_is_completed_by_the_same_finalizer(): void
    {
        config()->set('paypal.merchant_id', 'MERCHANT-RECONCILE');
        Mail::fake();
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);
        $image = EventsImges::create(['event_id' => $event->id, 'is_active' => '1', 'price' => '10.00']);
        $order = Purchases::create([
            'user_id' => $buyer->id, 'payment_method' => 'paypal', 'status' => 'approved', 'currency' => 'USD',
            'amount' => '10.00', 'amount_minor' => 1000, 'type' => 'checkout', 'order_type' => 'single_media',
            'idempotency_key' => 'reconcile-order',
        ]);
        $order->items()->create(['image_id' => $image->id, 'price' => '10.00', 'purchased_type' => 'single']);
        $payment = Payment::create([
            'order_id' => $order->id, 'user_id' => $buyer->id, 'operation' => 'purchase', 'method' => 'paypal',
            'status' => 'approved', 'amount_minor' => 1000, 'currency' => 'USD', 'idempotency_key' => 'reconcile-payment',
            'paypal_order_id' => 'PAYPAL-RECONCILE', 'custom_id' => 'purchase:1', 'reference_id' => '1',
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);
        $payment->update(['custom_id' => 'purchase:'.$payment->id, 'reference_id' => (string) $payment->id]);
        $order->update(['paypal_order_id' => $payment->paypal_order_id]);
        Wallet::create(['user_id' => $seller->id, 'amount' => '0.00', 'balance_minor' => 0, 'currency' => 'USD']);

        $gateway = Mockery::mock(PayPalGateway::class);
        $gateway->shouldReceive('showOrderDetails')->once()->with('PAYPAL-RECONCILE')->andReturn([
            'id' => 'PAYPAL-RECONCILE',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => (string) $payment->id,
                'custom_id' => 'purchase:'.$payment->id,
                'payee' => ['merchant_id' => 'MERCHANT-RECONCILE'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-RECONCILE',
                    'status' => 'COMPLETED',
                    'amount' => ['value' => '10.00', 'currency_code' => 'USD'],
                ]]],
            ]],
        ]);
        $this->app->instance(PayPalGateway::class, $gateway);

        $this->artisan('payments:reconcile', ['--limit' => 10, '--age' => 10])->assertSuccessful();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseHas('entitlements', ['user_id' => $buyer->id, 'media_id' => $image->id]);
        $this->assertDatabaseHas('wallet_transactions', ['source' => 'media_sale', 'amount_minor' => 1000]);
    }
}

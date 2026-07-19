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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class WalletPurchaseSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('purchaseTypeProvider')]
    public function test_wallet_purchase_types_debit_and_fulfill_atomically(string $type, int $expectedMinor): void
    {
        [$buyer, $event, $images] = $this->catalog();
        Wallet::create(['user_id' => $buyer->id, 'amount' => '100.00', 'balance_minor' => 10000, 'currency' => 'USD']);
        $payload = match ($type) {
            'single_media' => ['media_id' => $images[0]->id],
            'collection' => ['collection_id' => $event->id],
            'multiple_media' => ['media_ids' => [$images[0]->id, $images[1]->id, $images[0]->id]],
            'cart' => $this->cartPayload($buyer, $event, $images),
        };
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/pay/wallet', array_merge($payload, [
            'type' => $type,
            'idempotency_key' => 'wallet-'.$type,
            'amount' => '0.01',
        ]))->assertOk()->assertJson(['status' => 'completed']);

        $order = Purchases::findOrFail($response->json('order_id'));
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame($expectedMinor, $order->amount_minor);
        $this->assertSame(10000 - $expectedMinor, $buyer->wallet()->first()->balance_minor);
        $this->assertTrue($payment->purchase_granted);
        $this->assertSame($order->items()->count(), Entitlement::where('user_id', $buyer->id)->count());
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'type' => 'debit',
            'source' => 'content_purchase',
            'amount_minor' => $expectedMinor,
        ]);

        $this->postJson('/api/v1/pay/wallet', array_merge($payload, [
            'type' => $type, 'idempotency_key' => 'wallet-'.$type,
        ]))->assertOk();
        $this->assertSame(10000 - $expectedMinor, $buyer->wallet()->first()->balance_minor);
        $this->assertSame(1, $buyer->wallet()->first()->walletTransactions()->where('source', 'content_purchase')->count());
    }

    public static function purchaseTypeProvider(): array
    {
        return [
            'single' => ['single_media', 1000],
            'collection' => ['collection', 5400],
            'multiple' => ['multiple_media', 3000],
            'cart collection plus child is deduplicated' => ['cart', 5400],
        ];
    }

    public function test_insufficient_balance_rolls_back_order_payment_ledger_and_entitlement(): void
    {
        [$buyer, , $images] = $this->catalog();
        Wallet::create(['user_id' => $buyer->id, 'amount' => '1.00', 'balance_minor' => 100, 'currency' => 'USD']);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/pay/wallet', [
            'type' => 'single_media', 'media_id' => $images[0]->id, 'idempotency_key' => 'no-money',
        ])->assertStatus(422);

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('entitlements', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(100, $buyer->wallet()->first()->balance_minor);
    }

    public function test_status_routes_are_authenticated_owner_scoped_and_type_scoped(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $purchase = $this->bareOrder($owner, 'checkout');
        $deposit = $this->bareOrder($owner, 'wallet_deposit');

        $this->getJson('/api/v1/order/status/'.$purchase->id)->assertUnauthorized();
        Sanctum::actingAs($attacker);
        $this->getJson('/api/v1/order/status/'.$purchase->id)->assertNotFound();
        $this->getJson('/api/v1/wallet/order-status/'.$deposit->id)->assertNotFound();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/order/status/'.$purchase->id)->assertOk()->assertJsonMissing(['gateway_response' => []]);
        $this->getJson('/api/v1/order/status/'.$deposit->id)->assertNotFound();
        $this->getJson('/api/v1/wallet/order-status/'.$deposit->id)->assertOk();
        $this->getJson('/api/v1/wallet/order-status/'.$purchase->id)->assertNotFound();
    }

    public function test_download_requires_entitlement_and_never_accepts_a_client_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('owned/file.jpg', 'image bytes');
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $event = Events::create(['user_id' => User::factory()->create()->id, 'is_active' => true]);
        $media = EventsImges::create([
            'event_id' => $event->id, 'is_active' => '1', 'price' => '1.00',
            'full_url' => 'owned/file.jpg', 'preview_url' => 'previews/file.jpg', 'type' => 'image',
        ]);
        Entitlement::create([
            'user_id' => $owner->id, 'media_id' => $media->id, 'source' => 'purchase', 'granted_at' => now(),
        ]);

        Sanctum::actingAs($attacker);
        $this->get('/api/v1/download/'.$media->id)->assertForbidden();
        $this->get('/api/v1/download?path=owned/file.jpg')->assertNotFound();

        Sanctum::actingAs($owner);
        $this->get('/api/v1/download/'.$media->id)->assertOk()->assertDownload('file.jpg');
        $this->getJson('/api/v1/users/downloads')
            ->assertOk()
            ->assertJsonPath('data.0.id', $media->id)
            ->assertJsonMissingPath('data.0.full_url');
    }

    private function catalog(): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $event = Events::create(['user_id' => $seller->id, 'is_active' => true]);
        $images = collect([1000, 2000, 3000])->map(fn ($minor) => EventsImges::create([
            'event_id' => $event->id, 'is_active' => '1', 'price' => number_format($minor / 100, 2, '.', ''), 'type' => 'image',
        ]));
        return [$buyer, $event, $images];
    }

    private function cartPayload(User $buyer, Events $event, $images): array
    {
        $cart = Cart::create(['user_id' => $buyer->id]);
        CartItems::create([
            'cart_id' => $cart->id, 'event_id' => $event->id, 'type' => 'collection',
            'price' => '60.00', 'discount' => '6.00', 'collection_images' => $images->map(fn ($image) => ['id' => $image->id])->all(),
        ]);
        CartItems::create([
            'cart_id' => $cart->id, 'event_id' => $event->id, 'image_id' => $images[0]->id,
            'type' => 'single', 'price' => '10.00',
        ]);
        return [];
    }

    private function bareOrder(User $user, string $type): Purchases
    {
        static $sequence = 0;
        $sequence++;
        return Purchases::create([
            'user_id' => $user->id, 'payment_method' => 'paypal', 'status' => 'pending', 'currency' => 'USD',
            'amount' => '1.00', 'amount_minor' => 100, 'idempotency_key' => 'status-'.$sequence,
            'type' => $type, 'order_type' => $type,
        ]);
    }
}

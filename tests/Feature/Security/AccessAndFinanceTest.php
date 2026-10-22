<?php

namespace Tests\Feature\Security;

use App\Models\Entitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessAndFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'user', 'is_active' => true, 'email_verified_at' => now(), ...$attributes]);
    }

    private function event(User $owner, array $attributes = []): Events
    {
        return Events::create(['title' => 'Local audit event', 'description' => 'Fixture only', 'slug' => 'audit-'.uniqid(), 'is_active' => true, 'user_id' => $owner->id, ...$attributes]);
    }

    public function test_owning_one_event_does_not_authorize_deleting_another_event(): void
    {
        $attacker = $this->user();
        $this->event($attacker);
        $victim = $this->event($this->user());
        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/v1/user-dshboard/'.$victim->id.'/destroy')->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $victim->id]);
    }

    public function test_authenticated_user_cannot_delete_someone_elses_media(): void
    {
        $event = $this->event($this->user());
        $media = EventsImges::create(['event_id' => $event->id, 'full_url' => 'events/full/fixture.jpg', 'price' => 10]);
        Sanctum::actingAs($this->user());
        $this->deleteJson('/api/v1/user-dshboard/'.$media->id.'/delete')->assertForbidden();
        $this->assertDatabaseHas('events_images', ['id' => $media->id]);
    }

    public function test_input_cannot_shadow_the_authorized_route_event_on_delete(): void
    {
        $owner = $this->user();
        $own = $this->event($owner);
        $victim = $this->event($this->user());
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/user-dshboard/'.$own->id.'/destroy', ['id' => $victim->id])->assertOk();
        $this->assertDatabaseHas('events', ['id' => $victim->id]);
        $this->assertDatabaseMissing('events', ['id' => $own->id]);
    }

    public function test_input_cannot_shadow_the_authorized_route_event_on_update(): void
    {
        $owner = $this->user();
        $own = $this->event($owner);
        $victim = $this->event($this->user());
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/user-dshboard/'.$own->slug.'/update/Event', ['slug' => $victim->slug, 'title' => 'Corrected own event'])->assertOk();
        $this->assertSame('Local audit event', $victim->fresh()->title);
        $this->assertSame('Corrected own event', $own->fresh()->title);
    }

    public function test_event_update_cannot_assign_owner_or_publication_flags(): void
    {
        $owner = $this->user();
        $event = $this->event($owner, ['is_active' => false]);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/user-dshboard/'.$event->slug.'/update/Event', [
            'title' => 'Corrected title', 'user_id' => $this->user()->id, 'is_active' => true, 'is_trending' => true,
        ])->assertOk();
        $this->assertSame($owner->id, $event->fresh()->user_id);
        $this->assertFalse((bool) $event->fresh()->is_active);
        $this->assertFalse((bool) $event->fresh()->is_trending);
    }

    public function test_creator_detail_is_owner_scoped(): void
    {
        $event = $this->event($this->user());
        Sanctum::actingAs($this->user());
        $this->getJson('/api/v1/creator/show/'.$event->slug)->assertForbidden();
    }

    public function test_unrelated_user_cannot_upload_to_another_event(): void
    {
        Bus::fake();
        Storage::fake('public');
        $event = $this->event($this->user());
        Sanctum::actingAs($this->user());
        $this->postJson('/api/v1/user-dshboard/'.$event->slug, ['url' => [UploadedFile::fake()->image('photo.jpg')]])->assertForbidden();
    }

    public function test_upload_rejects_unbounded_or_uninspectable_files(): void
    {
        Bus::fake();
        $owner = $this->user();
        $event = $this->event($owner);
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/media-request/upload/'.$event->id, [
            'url' => [UploadedFile::fake()->createWithContent('photo.jpg', 'This is not an image')],
        ])->assertUnprocessable();
        $this->postJson('/api/v1/media-request/upload/'.$event->id, [
            'url' => [UploadedFile::fake()->image('too-large.jpg')->size(20461)],
        ])->assertUnprocessable();
    }

    public function test_disabling_an_account_invalidates_existing_token_access(): void
    {
        Storage::fake('public');
        $user = $this->user(['is_active' => false]);
        $media = EventsImges::create(['event_id' => $this->event($this->user())->id, 'full_url' => 'events/full/fixture.jpg', 'price' => 10]);
        Storage::disk('public')->put($media->full_url, 'fixture-original');
        Entitlement::create(['user_id' => $user->id, 'media_id' => $media->id, 'source' => 'test', 'granted_at' => now()]);
        $this->withToken($user->createToken('local-fixture')->plainTextToken)
            ->get('/api/v1/download/'.$media->id, ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_wallet_purchase_cannot_relabel_currency(): void
    {
        config(['paypal.currency' => 'EUR']);
        $buyer = $this->user();
        Wallet::create(['user_id' => $buyer->id, 'amount' => '100.00', 'balance_minor' => 10000, 'currency' => 'USD']);
        $media = EventsImges::create(['event_id' => $this->event($this->user())->id, 'is_active' => true, 'full_url' => 'fixture.jpg', 'price' => 10]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/pay/wallet', ['type' => 'single_media', 'media_id' => $media->id, 'idempotency_key' => 'currency-fixture'])->assertStatus(422);
        $this->assertSame('USD', $buyer->wallet()->first()->currency);
        $this->assertSame(10000, $buyer->wallet()->first()->balance_minor);
    }

    public function test_withdrawal_reserves_both_wallet_representations(): void
    {
        $owner = $this->user();
        $wallet = Wallet::create(['user_id' => $owner->id, 'amount' => '100.00', 'balance_minor' => 10000, 'pending' => 0, 'currency' => 'USD']);
        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/withdraw/requestWithdrawals/'.$wallet->id, ['amount' => 10, 'currency' => 'USD'])->assertOk();
        $this->assertSame(9000, $wallet->fresh()->balance_minor);
        $this->assertEquals(90, $wallet->fresh()->amount);
        $this->assertEquals(10, $wallet->fresh()->pending);
        $this->postJson('/api/v1/withdraw/updateWithdrawals/'.$response->json('data.id'), ['amount' => 20])->assertOk();
        $this->assertSame(8000, $wallet->fresh()->balance_minor);
        $this->assertEquals(20, $wallet->fresh()->pending);
    }
}

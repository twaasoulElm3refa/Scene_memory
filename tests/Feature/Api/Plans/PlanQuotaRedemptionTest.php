<?php

namespace Tests\Feature\Api\Plans;

use App\Models\DownloadEntitlement;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\LicenceType;
use App\Models\MediaTier;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\MediaTierService;
use App\Services\WalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanQuotaRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected MediaTier $basicTier;
    protected MediaTier $proTier;
    protected LicenceType $proPlan;
    protected User $buyer;
    protected User $creator;
    protected Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        MediaTier::withTrashed()->forceDelete();

        $this->basicTier = MediaTier::create([
            'name' => 'Basic',
            'rank' => 1,
            'min_price_photo' => 0.00,
            'max_price_photo' => 2.00,
            'payout_cap_photo' => 1.50,
            'min_price_video' => 0.00,
            'max_price_video' => 5.00,
            'payout_cap_video' => 3.50,
            'is_plan_eligible' => true,
            'is_active' => true,
        ]);

        $this->proTier = MediaTier::create([
            'name' => 'Pro',
            'rank' => 2,
            'min_price_photo' => 2.01,
            'max_price_photo' => 5.00,
            'payout_cap_photo' => 3.50,
            'min_price_video' => 5.01,
            'max_price_video' => 15.00,
            'payout_cap_video' => 10.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ]);

        $this->proPlan = LicenceType::create([
            'name' => 'Pro Plan',
            'slug' => 'pro-plan',
            'price' => 99.00,
            'media_tier_id' => $this->proTier->id,
            'photo_quota' => 10,
            'video_quota' => 2,
            'max_accumulated_photos' => 20,
            'max_accumulated_videos' => 5,
            'is_active' => true,
        ]);

        $this->buyer = User::factory()->create();
        \App\Models\Wallet::create([
            'user_id' => $this->buyer->id,
            'amount' => 200.00,
            'balance_minor' => 20000,
            'currency' => 'USD',
        ]);

        $this->creator = User::factory()->create();
        \App\Models\Wallet::create([
            'user_id' => $this->creator->id,
            'amount' => 0.00,
            'balance_minor' => 0,
            'currency' => 'USD',
        ]);

        $this->event = Events::create([
            'title' => 'Test Marketplace Event',
            'description' => 'Desc',
            'user_id' => $this->creator->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
    }

    public function test_user_can_purchase_plan_using_wallet(): void
    {
        $response = $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$this->proPlan->id}/wallet");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'total_photos' => 10,
            'total_videos' => 2,
        ]);

        // Assert buyer wallet was debited $99.00
        $buyerWallet = \App\Models\Wallet::where('user_id', $this->buyer->id)->first();
        $this->assertEquals(101.00, (float) $buyerWallet->amount);

        // Assert UserCreditGrant was created
        $this->assertDatabaseHas('user_credit_grants', [
            'user_id' => $this->buyer->id,
            'licence_id' => $this->proPlan->id,
            'remaining_photos' => 10,
            'remaining_videos' => 2,
            'payment_method' => 'wallet',
        ]);

        // Assert CreditTransaction was created
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->buyer->id,
            'action' => 'grant',
            'media_type' => 'photo',
            'quantity' => 10,
        ]);
    }

    public function test_user_cannot_exceed_max_accumulated_quota_limit(): void
    {
        // First purchase: gets 10 photos
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$this->proPlan->id}/wallet")->assertOk();

        // Second purchase: gets 10 photos -> 20 total (limit is 20)
        \App\Models\Wallet::where('user_id', $this->buyer->id)->update(['amount' => 500.00, 'balance_minor' => 50000]);
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$this->proPlan->id}/wallet")->assertOk();

        // Third purchase: would be 30 photos -> Exceeds max 20!
        $response = $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$this->proPlan->id}/wallet");
        $response->assertStatus(422);
        $this->assertStringContainsString('accumulation limit', $response->json('message'));
    }

    public function test_user_can_redeem_eligible_media_using_plan_credits(): void
    {
        // Buyer buys Pro Plan
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$this->proPlan->id}/wallet")->assertOk();

        // Creator lists a $4.50 photo (classified into Pro tier)
        $photo = EventsImges::create([
            'event_id' => $this->event->id,
            'preview_url' => 'pro_photo.jpg',
            'type' => 'image',
            'price' => 4.50,
            'is_active' => true,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo);
        $photo->refresh();
        $this->assertEquals('Pro', $photo->mediaTier->name);

        // Buyer redeems the photo
        $response = $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/media-credits/redeem/{$photo->id}");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'remaining_photos' => 9,
        ]);

        // Verify photo quota decremented to 9
        $this->assertEquals(9, $this->buyer->remainingPhotos());

        // Verify Entitlement created
        $this->assertDatabaseHas('entitlements', [
            'user_id' => $this->buyer->id,
            'media_id' => $photo->id,
            'source' => 'plan_redemption',
        ]);

        // Verify creator wallet received MIN(listing_price, payout_cap) = MIN(4.50, 3.50) = $3.50
        // with ZERO extra 15% platform deduction
        $creatorWallet = \App\Models\Wallet::where('user_id', $this->creator->id)->first();
        $this->assertEquals(3.50, (float) $creatorWallet->amount);

        // Verify CreditTransaction redemption audit entry
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->buyer->id,
            'media_id' => $photo->id,
            'action' => 'redemption',
            'quantity' => -1,
        ]);
    }

    public function test_user_cannot_redeem_media_exceeding_plan_tier_rank(): void
    {
        // Create Basic Plan (Rank 1)
        $basicPlan = LicenceType::create([
            'name' => 'Basic Plan',
            'slug' => 'basic-plan',
            'price' => 19.00,
            'media_tier_id' => $this->basicTier->id,
            'photo_quota' => 5,
            'video_quota' => 0,
            'is_active' => true,
        ]);

        // Buyer buys Basic Plan
        $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/plans/subscribe/{$basicPlan->id}/wallet")->assertOk();

        // Creator lists a Pro photo ($4.00, Rank 2)
        $proPhoto = EventsImges::create([
            'event_id' => $this->event->id,
            'preview_url' => 'pro_photo.jpg',
            'type' => 'image',
            'price' => 4.00,
            'is_active' => true,
        ]);
        app(MediaTierService::class)->assignTierToMedia($proPhoto);

        // Basic buyer attempts to redeem Pro photo -> Must be rejected!
        $response = $this->actingAs($this->buyer, 'sanctum')->postJson("/api/v1/media-credits/redeem/{$proPhoto->id}");
        $response->assertStatus(422);
        $this->assertStringContainsString('tier', strtolower($response->json('message')));
    }
}

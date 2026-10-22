<?php

namespace Tests\Feature\Api\Plans;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\MediaTier;
use App\Models\User;
use App\Services\MediaTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTierClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic tiers
        MediaTier::withTrashed()->forceDelete();

        MediaTier::create([
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

        MediaTier::create([
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

        MediaTier::create([
            'name' => 'Premium',
            'rank' => 3,
            'min_price_photo' => 5.01,
            'max_price_photo' => 20.00,
            'payout_cap_photo' => 12.00,
            'min_price_video' => 15.01,
            'max_price_video' => 50.00,
            'payout_cap_video' => 30.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ]);
    }

    private function createEvent(User $creator): Events
    {
        return Events::create([
            'title' => 'Test Event',
            'description' => 'Desc',
            'user_id' => $creator->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
    }

    public function test_photo_is_automatically_classified_into_correct_tier_based_on_price(): void
    {
        $creator = User::factory()->create();
        $event = $this->createEvent($creator);

        // $1.50 photo -> Basic
        $photo1 = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'test1.jpg',
            'type' => 'image',
            'price' => 1.50,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo1);
        $photo1->refresh();

        $this->assertNotNull($photo1->media_tier_id);
        $this->assertEquals('Basic', $photo1->mediaTier->name);
        $this->assertTrue($photo1->is_plan_redeemable);

        // $4.00 photo -> Pro
        $photo2 = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'test2.jpg',
            'type' => 'image',
            'price' => 4.00,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo2);
        $photo2->refresh();

        $this->assertNotNull($photo2->media_tier_id);
        $this->assertEquals('Pro', $photo2->mediaTier->name);

        // $15.00 photo -> Premium
        $photo3 = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'test3.jpg',
            'type' => 'image',
            'price' => 15.00,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo3);
        $photo3->refresh();

        $this->assertNotNull($photo3->media_tier_id);
        $this->assertEquals('Premium', $photo3->mediaTier->name);
    }

    public function test_video_is_automatically_classified_into_correct_tier_based_on_video_boundaries(): void
    {
        $creator = User::factory()->create();
        $event = $this->createEvent($creator);

        // $4.00 video -> Basic ($0 - $5)
        $video1 = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'test1.mp4',
            'type' => 'video',
            'price' => 4.00,
        ]);
        app(MediaTierService::class)->assignTierToMedia($video1);
        $video1->refresh();

        $this->assertEquals('Basic', $video1->mediaTier->name);

        // $10.00 video -> Pro ($5.01 - $15)
        $video2 = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'test2.mp4',
            'type' => 'video',
            'price' => 10.00,
        ]);
        app(MediaTierService::class)->assignTierToMedia($video2);
        $video2->refresh();

        $this->assertEquals('Pro', $video2->mediaTier->name);
    }

    public function test_media_exceeding_highest_ceiling_is_marked_not_plan_redeemable(): void
    {
        $creator = User::factory()->create();
        $event = $this->createEvent($creator);

        // $100.00 photo exceeds highest tier ceiling of $20.00
        $photo = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'expensive.jpg',
            'type' => 'image',
            'price' => 100.00,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo);
        $photo->refresh();

        $this->assertNull($photo->media_tier_id);
        $this->assertFalse($photo->is_plan_redeemable);
    }

    public function test_media_price_update_recalculates_tier(): void
    {
        $creator = User::factory()->create();
        $event = $this->createEvent($creator);

        $photo = EventsImges::create([
            'event_id' => $event->id,
            'preview_url' => 'dynamic.jpg',
            'type' => 'image',
            'price' => 1.50,
        ]);
        app(MediaTierService::class)->assignTierToMedia($photo);
        $this->assertEquals('Basic', $photo->fresh()->mediaTier->name);

        // Creator raises price to $4.50
        $photo->update(['price' => 4.50]);
        app(MediaTierService::class)->assignTierToMedia($photo);
        $this->assertEquals('Pro', $photo->fresh()->mediaTier->name);
    }

    public function test_admin_can_configure_a_higher_tier_above_twenty_dollars(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/media-tiers', [
            'name' => 'Ultra',
            'rank' => 4,
            'min_photo_price' => 20.01,
            'max_photo_price' => 50.00,
            'max_photo_payout_cap' => 40.00,
            'min_video_price' => 50.01,
            'max_video_price' => 100.00,
            'max_video_payout_cap' => 80.00,
            'is_plan_eligible' => true,
            'is_active' => true,
        ])->assertOk();

        $ultra = MediaTier::findOrFail($response->json('data.id'));
        $service = app(MediaTierService::class);

        $this->assertSame('Pro', $service->classify('image', 5.00)?->name);
        $this->assertSame('Ultra', $service->classify('image', 25.00)?->name);
        $this->assertNull($service->classify('image', 50.01));
        $this->assertEquals(50.00, $ultra->maxEligiblePhotoPrice());
        $this->assertEquals(100.00, $ultra->maxEligibleVideoPrice());
    }
}

<?php

namespace Tests\Feature\Points;

use App\Models\CommentImage;
use App\Models\Events;
use App\Models\Likes;
use App\Models\PointRule;
use App\Models\User;
use App\Models\UserDailyPoint;
use App\Models\UserPointHistory;
use App\Models\Wishlist;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PointServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');
    }

    public function test_it_awards_all_supported_timeline_actions_and_aggregates_daily_points(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $event = app(EventRepositoryInterface::class)->create([
            'user_id' => $user->id,
            'title' => 'Point event',
            'slug' => 'point-event',
        ]);

        $likeRepository = app(LikeRepositoryInterface::class);
        $likeRepository->create(['user_id' => $user->id, 'event_id' => $event->id]);
        $likeRepository->create(['user_id' => $user->id, 'event_id' => $event->id]);

        $commentRepository = app(CommentRepositoryInterface::class);
        $comment = $commentRepository->create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'comment' => 'Point comment',
        ]);

        $commentImage = CommentImage::create([
            'comment_id' => $comment->id,
            'path' => 'comments/point-image.jpg',
            'disk' => 'public',
        ]);
        app(PointService::class)->award(
            $user,
            PointService::COMMENT_IMAGE,
            $commentImage,
            ['comment_id' => $comment->id]
        );

        $commentRepository->createReply([
            'user_id' => $user->id,
            'comment_id' => $comment->id,
            'comment' => 'Point reply',
        ]);

        $commentRepository->updateOrCreateInteraction(
            ['user_id' => $user->id, 'comment_id' => $comment->id],
            ['type' => 'support']
        );
        $commentRepository->updateOrCreateInteraction(
            ['user_id' => $user->id, 'comment_id' => $comment->id],
            ['type' => 'neutral']
        );

        $wishlistRepository = app(WishlistRepositoryInterface::class);
        $wishlistRepository->firstOrCreate(['user_id' => $user->id, 'event_id' => $event->id]);
        $wishlistRepository->firstOrCreate(['user_id' => $user->id, 'event_id' => $event->id]);

        $this->assertSame(23, $user->fresh()->total_points);
        $this->assertSame(7, UserPointHistory::where('user_id', $user->id)->count());
        $this->assertSame(7, UserDailyPoint::where('user_id', $user->id)->count());
        $this->assertSame(
            23,
            UserDailyPoint::where('user_id', $user->id)->sum('points')
        );
        $this->assertSame(1, Likes::where('user_id', $user->id)->where('event_id', $event->id)->count());
        $this->assertSame(1, Wishlist::where('user_id', $user->id)->where('event_id', $event->id)->count());
    }

    public function test_award_is_idempotent_for_the_same_user_action_and_reference(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $event = Events::create([
            'user_id' => $user->id,
            'title' => 'Idempotent event',
            'slug' => 'idempotent-event',
        ]);
        $service = app(PointService::class);

        $this->assertTrue($service->award($user, PointService::EVENT_CREATED, $event));
        $this->assertFalse($service->award($user, PointService::EVENT_CREATED, $event));

        $this->assertSame(10, $user->fresh()->total_points);
        $this->assertDatabaseHas('user_daily_points', [
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'action' => PointService::EVENT_CREATED,
            'count' => 1,
            'points' => 10,
        ]);
        $this->assertSame(1, UserPointHistory::where('user_id', $user->id)->count());
    }

    public function test_comment_endpoint_awards_the_comment_and_each_stored_image_atomically(): void
    {
        Queue::fake();
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'user']);
        $event = Events::create([
            'user_id' => $user->id,
            'title' => 'Comment image event',
            'slug' => 'comment-image-event',
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/comments/{$event->id}/create", [
            'comment' => 'Comment with an image',
            'images' => [UploadedFile::fake()->createWithContent(
                'comment.png',
                base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
                )
            )],
        ])->assertOk();

        $this->assertSame(6, $user->fresh()->total_points);
        $this->assertDatabaseHas('user_daily_points', [
            'user_id' => $user->id,
            'action' => PointService::COMMENT_CREATED,
            'count' => 1,
            'points' => 3,
        ]);
        $this->assertDatabaseHas('user_daily_points', [
            'user_id' => $user->id,
            'action' => PointService::COMMENT_IMAGE,
            'count' => 1,
            'points' => 3,
        ]);
    }

    public function test_an_inactive_or_unknown_rule_does_not_change_points(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $event = Events::create([
            'user_id' => $user->id,
            'title' => 'No points event',
            'slug' => 'no-points-event',
        ]);
        PointRule::where('action', PointService::EVENT_CREATED)->update(['status' => false]);
        $service = app(PointService::class);

        $this->assertFalse($service->award($user, PointService::EVENT_CREATED, $event));
        $this->assertFalse($service->award($user, 'unknown.action', $event));

        $this->assertSame(0, $user->fresh()->total_points);
        $this->assertDatabaseCount('user_daily_points', 0);
        $this->assertDatabaseCount('user_points_history', 0);
    }
}

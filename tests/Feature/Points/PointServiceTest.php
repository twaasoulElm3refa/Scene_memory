<?php

namespace Tests\Feature\Points;

use App\Models\CommentImage;
use App\Models\Events;
use App\Models\Likes;
use App\Models\PointRule;
use App\Models\User;
use App\Models\UserDailyPoint;
use App\Models\UserPointHistory;
use App\Models\UserWeeklyPoint;
use App\Models\WeeklyLeaderboard;
use App\Models\Wishlist;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;
use App\Services\PointService;
use App\Services\WeeklyCompetitionPeriod;
use App\Services\WeeklyLeaderboardService;
use App\Services\WeeklyPointService;
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

    public function test_it_awards_all_supported_timeline_actions_and_aggregates_weekly_points(): void
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
        $this->assertDatabaseHas('user_weekly_points', [
            'user_id' => $user->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 23,
        ]);
        $this->assertSame(7, UserDailyPoint::where('user_id', $user->id)->count());
        $this->assertSame(23, UserDailyPoint::where('user_id', $user->id)->sum('points'));
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
        $this->assertDatabaseHas('user_weekly_points', [
            'user_id' => $user->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 10,
        ]);
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
        $this->assertDatabaseHas('user_weekly_points', [
            'user_id' => $user->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 6,
        ]);
        $this->assertSame(2, UserDailyPoint::where('user_id', $user->id)->count());
        $this->assertSame(6, UserDailyPoint::where('user_id', $user->id)->sum('points'));
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
        $this->assertDatabaseCount('user_weekly_points', 0);
        $this->assertDatabaseCount('user_points_history', 0);
    }

    public function test_weekly_service_accepts_reference_type_and_id_and_prevents_duplicates(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $event = Events::create([
            'user_id' => $user->id,
            'title' => 'Direct weekly award',
            'slug' => 'direct-weekly-award',
        ]);
        $service = app(WeeklyPointService::class);

        $this->assertTrue($service->addPoints(
            $user,
            PointService::COMMENT_CREATED,
            Events::class,
            $event->id
        ));
        $this->assertFalse($service->addPoints(
            $user,
            PointService::COMMENT_CREATED,
            Events::class,
            $event->id
        ));

        $this->assertSame(3, $user->fresh()->total_points);
        $this->assertSame(3, $user->weeklyPoints()->sole()->points);
        $this->assertSame(1, $user->pointsHistory()->count());
    }

    public function test_current_week_leaderboard_is_ranked_and_paginated(): void
    {
        $users = User::factory()->count(3)->create(['role' => 'user']);

        UserWeeklyPoint::create(['user_id' => $users[0]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 210]);
        UserWeeklyPoint::create(['user_id' => $users[1]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 520]);
        UserWeeklyPoint::create(['user_id' => $users[2]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 430]);

        $firstPage = app(WeeklyLeaderboardService::class)->currentWeek(2, 1);
        $secondPage = app(WeeklyLeaderboardService::class)->currentWeek(2, 2);

        $this->assertSame([520, 430], $firstPage->pluck('points')->all());
        $this->assertSame([1, 2], $firstPage->pluck('rank')->all());
        $this->assertSame([210], $secondPage->pluck('points')->all());
        $this->assertSame([3], $secondPage->pluck('rank')->all());
        $this->assertTrue($firstPage->first()->relationLoaded('user'));
    }

    public function test_current_user_weekly_stats_reuse_live_leaderboard_ordering(): void
    {
        $users = User::factory()->count(3)->create(['role' => 'user']);

        UserWeeklyPoint::create(['user_id' => $users[0]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 250]);
        UserWeeklyPoint::create(['user_id' => $users[1]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 500]);
        UserWeeklyPoint::create(['user_id' => $users[2]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 100]);

        $service = app(WeeklyLeaderboardService::class);

        $this->assertSame([
            'weekly_points' => 250,
            'weekly_rank' => 2,
            ...app(WeeklyCompetitionPeriod::class)->metadata(),
        ], $service->currentUserStats($users[0]->id));

        $users[0]->weeklyPoints()->update(['points' => 600]);

        $this->assertSame([
            'weekly_points' => 600,
            'weekly_rank' => 1,
            ...app(WeeklyCompetitionPeriod::class)->metadata(),
        ], $service->currentUserStats($users[0]->id));
    }

    public function test_user_without_current_week_points_is_unranked(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertSame([
            'weekly_points' => 0,
            'weekly_rank' => null,
            ...app(WeeklyCompetitionPeriod::class)->metadata(),
        ], app(WeeklyLeaderboardService::class)->currentUserStats($user->id));
    }

    public function test_archive_command_preserves_completed_aggregates_and_lifetime_points(): void
    {
        Carbon::setTestNow('2026-10-01 00:00:00');
        $users = User::factory()->count(2)->create(['role' => 'user']);
        $users[0]->update(['total_points' => 520]);
        $users[1]->update(['total_points' => 430]);

        UserWeeklyPoint::create(['user_id' => $users[0]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 520]);
        UserWeeklyPoint::create(['user_id' => $users[1]->id, 'week_start' => '2026-09-12', 'week_end' => '2026-09-18', 'points' => 430]);

        $this->artisan('leaderboard:archive-weekly')
            ->assertSuccessful();

        $this->assertDatabaseHas('weekly_leaderboards', [
            'user_id' => $users[0]->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 520,
            'rank' => 1,
        ]);
        $this->assertDatabaseHas('weekly_leaderboards', [
            'user_id' => $users[1]->id,
            'points' => 430,
            'rank' => 2,
        ]);
        $this->assertDatabaseCount('user_weekly_points', 2);
        $this->assertSame(520, $users[0]->fresh()->total_points);
        $this->assertSame(430, $users[1]->fresh()->total_points);

        $this->artisan('leaderboard:archive-weekly')->assertSuccessful();
        $this->assertSame(2, WeeklyLeaderboard::count());
    }

    public function test_new_week_starts_from_zero_until_points_are_earned(): void
    {
        $user = User::factory()->create(['role' => 'user', 'total_points' => 10]);
        UserWeeklyPoint::create([
            'user_id' => $user->id,
            'week_start' => '2026-09-05',
            'week_end' => '2026-09-11',
            'points' => 10,
        ]);

        $leaderboard = app(WeeklyLeaderboardService::class)->currentWeek();

        $this->assertTrue($leaderboard->isEmpty());
        $this->assertSame(10, $user->fresh()->total_points);
    }
}

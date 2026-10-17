<?php

namespace Tests\Feature\Points;

use App\Models\MonthlyLeaderboard;
use App\Models\User;
use App\Models\UserMonthlyPoint;
use App\Models\UserPointHistory;
use App\Models\UserWeeklyPoint;
use App\Models\WeeklyLeaderboard;
use App\Services\WeeklyLeaderboardService;
use App\Services\WeeklyPointService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeeklyRolloverTest extends TestCase
{
    use RefreshDatabase;

    private function freeze(string $time): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($time, 'Asia/Riyadh'));
    }

    private function points(User $user, string $start, int $points): void
    {
        UserWeeklyPoint::create([
            'user_id' => $user->id, 'week_start' => $start,
            'week_end' => CarbonImmutable::parse($start)->addDays(6)->toDateString(), 'points' => $points,
        ]);
    }

    public function test_boundary_rollover_preserves_lifetime_and_history_before_archiving(): void
    {
        $user = User::factory()->create(['total_points' => 5000]);
        $this->freeze('2026-10-23 23:59:59');
        $this->points($user, '2026-10-17', 250);
        // A legacy/monthly cache must never influence a weekly query.
        Cache::put('leaderboard:monthly:2026-10', ['points' => 9999], 600);
        Cache::put('leaderboard:weekly:2026-10-17', ['points' => 250], 600);
        $this->getJson('/api/leaderboard/weekly-preview')->assertJsonPath('users.0.weekly_points', 250);

        $this->freeze('2026-10-24 00:00:00');
        $this->getJson('/api/leaderboard/weekly-preview')->assertJsonPath('week_start', '2026-10-24')->assertJsonCount(0, 'users');
        $stats = app(WeeklyLeaderboardService::class)->currentUserStats($user->id);
        $this->assertSame(0, $stats['weekly_points']);
        $this->assertNull($stats['weekly_rank']);
        $this->assertSame(5000, $user->fresh()->total_points);
        $this->assertDatabaseHas('user_weekly_points', ['week_start' => '2026-10-17', 'points' => 250]);
    }

    public function test_awards_on_both_sides_of_midnight_are_atomic_and_retries_crossing_weeks_are_deduplicated(): void
    {
        $user = User::factory()->create(['total_points' => 5000]);
        $service = app(WeeklyPointService::class);
        $this->freeze('2026-10-23 23:59:59');
        $this->assertTrue($service->addPoints($user, 'event.created', 'event', 101));
        $this->freeze('2026-10-24 00:00:00');
        $this->assertFalse($service->addPoints($user, 'event.created', 'event', 101));
        $this->assertTrue($service->addPoints($user, 'event.created', 'event', 102));
        $this->assertSame(5020, $user->fresh()->total_points);
        $this->assertSame(2, UserPointHistory::count());
        $this->assertSame(10, UserWeeklyPoint::where('week_start', '2026-10-17')->sole()->points);
        $this->assertSame(10, UserWeeklyPoint::where('week_start', '2026-10-24')->sole()->points);
        $this->assertSame('2026-10-23 21:00:00', UserPointHistory::latest('id')->first()->getRawOriginal('created_at'));
        $this->assertSame(20, (int) DB::table('user_daily_points')->sum('points'));
    }

    public function test_archive_retries_catch_up_empty_and_missed_weeks_without_mutating_finalized_results(): void
    {
        $this->freeze('2026-10-17 00:00:00');
        $user = User::factory()->create(['total_points' => 250]);
        $this->points($user, '2026-10-17', 250);
        UserMonthlyPoint::create(['user_id' => $user->id, 'month' => 9, 'year' => 2026, 'points' => 400]);
        MonthlyLeaderboard::create(['user_id' => $user->id, 'month' => 8, 'year' => 2026, 'points' => 400, 'rank' => 1]);
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-10-17')->assertNotFound();

        $this->freeze('2026-11-07 00:06:00');
        $this->points($user, '2026-11-07', 12);
        $this->artisan('leaderboard:archive-weekly')->assertSuccessful();
        $this->assertDatabaseHas('competition_weeks', ['week_start' => '2026-10-24']);
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-10-24')->assertOk()->assertJsonCount(0, 'users');
        $snapshot = WeeklyLeaderboard::where('week_start', '2026-10-17')->sole()->toArray();
        $this->assertSame(250, $snapshot['points']);
        // Finalization is immutable even if an aggregate is changed outside the scoring service.
        UserWeeklyPoint::where('week_start', '2026-10-17')->update(['points' => 999]);
        $this->artisan('leaderboard:archive-weekly')->assertSuccessful();
        $this->artisan('leaderboard:archive-month')->assertSuccessful();
        $this->assertSame($snapshot, WeeklyLeaderboard::where('week_start', '2026-10-17')->sole()->toArray());
        $this->assertDatabaseCount('weekly_leaderboards', 1);
        $this->assertDatabaseCount('user_weekly_points', 2);
        $this->assertDatabaseCount('user_monthly_points', 1);
        $this->assertDatabaseCount('monthly_leaderboards', 1);
        $this->assertSame(250, $user->fresh()->total_points);
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-10-17')->assertOk()->assertJsonPath('users.0.points', 250);
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-11-07')->assertNotFound();
        $this->getJson('/api/leaderboard/weekly-preview')->assertJsonPath('users.0.points', 12);
    }

    public function test_ties_pagination_and_profile_timeline_use_identical_global_positions(): void
    {
        $this->freeze('2026-10-20 12:00:00');
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $this->points($user, '2026-10-17', 250);
        }
        $this->getJson('/api/leaderboard/weekly-preview')->assertJsonPath('users.1.id', $users[1]->id)->assertJsonPath('users.1.weekly_rank', 2);
        $this->getJson('/api/leaderboard/weekly?per_page=1&page=2')->assertOk()->assertJsonPath('users.0.id', $users[1]->id)->assertJsonPath('users.0.weekly_rank', 2);
        Sanctum::actingAs($users[1]);
        $profile = $this->getJson('/api/v1/users/profile')->assertOk()->json('data.weekly_statistics');
        $timeline = $this->getJson('/api/v1/users/timeline?section=all&period=all&timezone=America/New_York')->assertOk()->json('data.weekly_statistics');
        $this->assertSame($profile, $timeline);
        $this->assertSame(2, $profile['weekly_rank']);
        $this->assertSame('2026-10-17', $profile['week_start']);
    }

    public function test_history_validates_identifiers_and_excludes_unfinalized_weeks(): void
    {
        $this->freeze('2026-10-24 00:06:00');
        $this->artisan('leaderboard:archive-weekly')->assertSuccessful();
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-10-18')->assertUnprocessable();
        $this->getJson('/api/leaderboard/weekly-history?week_start=2026-02-30')->assertUnprocessable();
        $this->getJson('/api/leaderboard/weekly-history?week_start=nonsense')->assertUnprocessable();
        $this->getJson('/api/leaderboard/weekly-history?per_page=101')->assertUnprocessable();
        $this->getJson('/api/leaderboard/weekly-history')->assertOk()->assertJsonPath('data.0.week_start', '2026-10-17');
        $this->getJson('/api/leaderboard/weekly?per_page=101')->assertUnprocessable();
        $this->getJson('/api/leaderboard/monthly-preview')->assertOk()->assertHeader('Deprecation', 'true');
    }

    public function test_point_transaction_rolls_back_all_totals_when_history_fails(): void
    {
        $this->freeze('2026-10-20 12:00:00');
        $user = User::factory()->create();
        UserWeeklyPoint::creating(function (): void {
            throw new \RuntimeException('Simulated database failure');
        });
        try {
            app(WeeklyPointService::class)->addPoints($user, 'event.created', 'event', 111);
            $this->fail('Expected transaction failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated database failure', $exception->getMessage());
        } finally {
            UserWeeklyPoint::flushEventListeners();
        }
        $this->assertSame(0, $user->fresh()->total_points);
        $this->assertDatabaseCount('user_points_history', 0);
        $this->assertDatabaseCount('user_weekly_points', 0);
        $this->assertDatabaseCount('user_daily_points', 0);
    }

    public function test_finalized_positions_survive_account_deletion_without_exposing_private_data(): void
    {
        $this->freeze('2026-10-24 00:06:00');
        $user = User::factory()->create();
        $this->points($user, '2026-10-17', 250);
        $this->artisan('leaderboard:archive-weekly')->assertSuccessful();
        $user->delete();
        $response = $this->getJson('/api/leaderboard/weekly-history?week_start=2026-10-17')
            ->assertOk()->assertJsonPath('users.0.points', 250)->assertJsonPath('users.0.rank', 1)
            ->assertJsonPath('users.0.name', null);
        $this->assertArrayNotHasKey('email', $response->json('users.0'));
        $this->assertArrayNotHasKey('phone', $response->json('users.0'));
    }

    public function test_scheduler_uses_saturday_and_the_configured_business_timezone(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'leaderboard:archive-weekly'));
        $this->assertNotNull($event);
        $this->assertSame('5 0 * * 6', $event->expression);
        $this->assertSame('Asia/Riyadh', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }
}

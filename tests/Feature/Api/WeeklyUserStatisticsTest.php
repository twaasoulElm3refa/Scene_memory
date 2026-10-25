<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Wallet;
use App\Models\UserWeeklyPoint;
use App\Services\WeeklyCompetitionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeeklyUserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');
    }

    public function test_profile_and_timeline_return_the_same_current_week_statistics(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $leader = User::factory()->create(['role' => 'user']);

        UserWeeklyPoint::create([
            'user_id' => $user->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 250,
        ]);
        UserWeeklyPoint::create([
            'user_id' => $leader->id,
            'week_start' => '2026-09-12',
            'week_end' => '2026-09-18',
            'points' => 500,
        ]);

        Sanctum::actingAs($user);

        $profile = $this->getJson('/api/v1/users/profile')->assertOk();
        $timeline = $this->getJson('/api/v1/users/timeline?section=all&period=all&timezone=UTC')->assertOk();

        $expected = [
            'weekly_points' => 250,
            'weekly_rank' => 2,
            ...app(WeeklyCompetitionPeriod::class)->metadata(),
        ];

        $profile->assertJsonPath('data.weekly_statistics', $expected);
        $timeline->assertJsonPath('data.weekly_statistics', $expected);
    }

    public function test_user_without_weekly_points_receives_zero_points_and_null_rank(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('data.weekly_statistics.weekly_points', 0)
            ->assertJsonPath('data.weekly_statistics.weekly_rank', null)
            ->assertJsonPath('data.weekly_statistics.week_start', '2026-09-12');
    }

    public function test_profile_balance_is_fresh_after_a_wallet_ledger_update(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/v1/users/profile')->assertOk();
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));

        Wallet::where('user_id', $user->id)->update(['amount' => '25.34', 'balance_minor' => 2534]);

        $this->getJson('/api/v1/users/profile')->assertOk()
            ->assertJsonPath('data.user.wallet.amount', '25.34')
            ->assertJsonPath('data.user.wallet.balance_minor', 2534);
        $this->getJson('/api/v1/users/wallet')->assertOk()
            ->assertJsonPath('data.amount', '25.34');
    }
}

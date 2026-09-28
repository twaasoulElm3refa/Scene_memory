<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserMonthlyPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyUserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-15 12:00:00');
    }

    public function test_profile_and_timeline_return_the_same_current_month_statistics(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $leader = User::factory()->create(['role' => 'user']);

        UserMonthlyPoint::create([
            'user_id' => $user->id,
            'month' => 9,
            'year' => 2026,
            'points' => 250,
        ]);
        UserMonthlyPoint::create([
            'user_id' => $leader->id,
            'month' => 9,
            'year' => 2026,
            'points' => 500,
        ]);

        Sanctum::actingAs($user);

        $profile = $this->getJson('/api/v1/users/profile')->assertOk();
        $timeline = $this->getJson('/api/v1/users/timeline?section=all&period=all&timezone=UTC')->assertOk();

        $expected = [
            'monthly_points' => 250,
            'monthly_rank' => 2,
            'month' => '2026-09',
        ];

        $profile->assertJsonPath('data.monthly_statistics', $expected);
        $timeline->assertJsonPath('data.monthly_statistics', $expected);
    }

    public function test_user_without_monthly_points_receives_zero_points_and_null_rank(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/profile')
            ->assertOk()
            ->assertJsonPath('data.monthly_statistics.monthly_points', 0)
            ->assertJsonPath('data.monthly_statistics.monthly_rank', null)
            ->assertJsonPath('data.monthly_statistics.month', '2026-09');
    }
}

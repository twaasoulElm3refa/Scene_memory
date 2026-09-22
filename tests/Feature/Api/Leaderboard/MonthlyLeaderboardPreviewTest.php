<?php

namespace Tests\Feature\Api\Leaderboard;

use App\Models\User;
use App\Models\UserMonthlyPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyLeaderboardPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-22 12:00:00');
    }

    public function test_it_returns_only_the_current_month_top_ten_in_rank_order(): void
    {
        $users = User::factory()->count(12)->create(['role' => 'user']);

        foreach ($users as $index => $user) {
            UserMonthlyPoint::create([
                'user_id' => $user->id,
                'month' => 9,
                'year' => 2026,
                'points' => ($index + 1) * 10,
            ]);
        }

        $previousMonthUser = User::factory()->create(['role' => 'user']);
        UserMonthlyPoint::create([
            'user_id' => $previousMonthUser->id,
            'month' => 8,
            'year' => 2026,
            'points' => 9999,
        ]);

        $response = $this->getJson('/api/leaderboard/monthly-preview');

        $response
            ->assertOk()
            ->assertJsonPath('month', 'September')
            ->assertJsonPath('year', 2026)
            ->assertJsonCount(10, 'users')
            ->assertJsonStructure([
                'month',
                'year',
                'users' => [[
                    'rank',
                    'id',
                    'name',
                    'avatar',
                    'points',
                    'badge',
                ]],
            ]);

        $this->assertSame(range(1, 10), collect($response->json('users'))->pluck('rank')->all());
        $this->assertSame(
            [120, 110, 100, 90, 80, 70, 60, 50, 40, 30],
            collect($response->json('users'))->pluck('points')->all()
        );
        $this->assertSame('Memory Master', $response->json('users.0.badge'));
        $this->assertSame('Story Keeper', $response->json('users.1.badge'));
        $this->assertSame('Moment Curator', $response->json('users.2.badge'));
        $this->assertNotContains($previousMonthUser->id, collect($response->json('users'))->pluck('id'));
    }

    public function test_it_returns_an_empty_user_list_when_no_points_exist_for_the_current_month(): void
    {
        $this->getJson('/api/leaderboard/monthly-preview')
            ->assertOk()
            ->assertExactJson([
                'month' => 'September',
                'year' => 2026,
                'users' => [],
            ]);
    }
}

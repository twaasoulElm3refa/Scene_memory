<?php

namespace Tests\Feature\Points;

use App\Models\User;
use App\Models\UserWeeklyPoint;
use App\Models\WeeklyLeaderboard;
use App\Services\WeeklyPointService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Run separately against a disposable MySQL database; never resets a database. */
class WeeklyConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('WEEKLY_MYSQL_CONCURRENCY')) {
            $this->markTestSkipped('Opt-in MySQL concurrency test; see docs/weekly-ranking.md.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringStartsWith('scemory_weekly_test', config('database.connections.mysql.database'));
        $this->assertTrue(function_exists('pcntl_fork'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24 00:06:00', 'Asia/Riyadh'));
    }

    private function concurrently(int $workers, callable $work): void
    {
        // Fork with no open PDO socket; every child gets its own connection.
        DB::disconnect();
        $children = [];
        for ($index = 0; $index < $workers; $index++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);
            if ($pid === 0) {
                try {
                    DB::purge();
                    $work($index);
                    exit(0);
                } catch (\Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage().PHP_EOL);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();
    }

    public function test_simultaneous_distinct_awards_and_duplicate_retries_have_exactly_once_totals(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->concurrently(8, function (int $index) use ($user): void {
            app(WeeklyPointService::class)->addPoints(User::findOrFail($user->id), 'event.created', 'concurrency', 9000 + ($index % 4));
        });
        $this->assertSame(40, $user->fresh()->total_points);
        $this->assertSame(40, $user->weeklyPoints()->sole()->points);
        $this->assertSame(4, $user->pointsHistory()->count());
        $this->assertSame(4, $user->dailyPoints()->sole()->count);
    }

    public function test_concurrent_archivers_and_new_week_awards_preserve_both_periods(): void
    {
        $user = User::factory()->create(['role' => 'user', 'total_points' => 250]);
        UserWeeklyPoint::create(['user_id' => $user->id, 'week_start' => '2026-10-17', 'week_end' => '2026-10-23', 'points' => 250]);
        $this->concurrently(6, function (int $index) use ($user): void {
            if ($index < 2) {
                if (Artisan::call('leaderboard:archive-weekly') !== 0) {
                    throw new \RuntimeException('Archive failed');
                }
            } else {
                app(WeeklyPointService::class)->addPoints(User::findOrFail($user->id), 'event.created', 'concurrent-archive', 9100 + ($index % 2));
            }
        });
        $this->assertSame(270, $user->fresh()->total_points);
        $this->assertSame(20, $user->weeklyPoints()->where('week_start', '2026-10-24')->sole()->points);
        $archive = WeeklyLeaderboard::where('user_id', $user->id)->sole();
        $this->assertSame(250, $archive->points);
        $this->assertSame('2026-10-17', $archive->week_start);
        $this->assertNull(DB::table('competition_weeks')->where('week_start', '2026-10-24')->value('finalized_at'));
    }
}

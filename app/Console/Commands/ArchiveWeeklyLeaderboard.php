<?php

namespace App\Console\Commands;

use App\Models\UserWeeklyPoint;
use App\Services\WeeklyCompetitionPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveWeeklyLeaderboard extends Command
{
    protected $signature = 'leaderboard:archive-weekly';

    protected $description = 'Finalize completed Saturday–Friday competitions, including missed weeks';

    public function handle(WeeklyCompetitionPeriod $period): int
    {
        $current = $period->currentWeekStart();
        $earliest = collect([
            DB::table('competition_weeks')->whereNull('finalized_at')->min('week_start'),
            UserWeeklyPoint::query()->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('competition_weeks')
                    ->whereColumn('competition_weeks.week_start', 'user_weekly_points.week_start')
                    ->whereNotNull('finalized_at');
            })->min('week_start'),
            ($last = DB::table('competition_weeks')->whereNotNull('finalized_at')->max('week_start'))
                ? CarbonImmutable::parse($last, $period->timezone())->addWeek()->toDateString() : null,
            $current->subWeek()->toDateString(),
        ])->filter()->min();
        $start = CarbonImmutable::parse($earliest, $period->timezone());
        $weeks = 0;

        for (; $start->lessThan($current); $start = $start->addWeek()) {
            $finalized = DB::transaction(function () use ($start, $period): bool {
                $metadata = $period->metadata($start);
                DB::table('competition_weeks')->insertOrIgnore([
                    'week_start' => $metadata['week_start'],
                    'week_end' => $metadata['week_end'],
                    'timezone' => $metadata['timezone'],
                ]);
                $week = DB::table('competition_weeks')->where('week_start', $metadata['week_start'])
                    ->lockForUpdate()->first();

                if ($week->finalized_at !== null) {
                    return false;
                }

                $rank = 0;
                $timestamp = CarbonImmutable::now('UTC');
                UserWeeklyPoint::query()->where('week_start', $metadata['week_start'])
                    ->where('points', '>', 0)->orderByDesc('points')->orderBy('user_id')
                    ->chunk(500, function ($entries) use (&$rank, $metadata, $timestamp): void {
                        $rows = [];
                        foreach ($entries as $entry) {
                            $rows[] = [
                                'user_id' => $entry->user_id,
                                'week_start' => $metadata['week_start'],
                                'week_end' => $metadata['week_end'],
                                'points' => $entry->points,
                                'rank' => ++$rank,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ];
                        }
                        DB::table('weekly_leaderboards')->insert($rows);
                    });

                DB::table('competition_weeks')->where('week_start', $metadata['week_start'])
                    ->update(['finalized_at' => $timestamp]);

                return true;
            }, 5);
            $weeks += (int) $finalized;
        }

        $this->info("Finalized {$weeks} completed weekly competitions.");

        return self::SUCCESS;
    }
}

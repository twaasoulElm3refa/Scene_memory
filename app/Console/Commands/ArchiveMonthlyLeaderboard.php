<?php

namespace App\Console\Commands;

use App\Models\MonthlyLeaderboard;
use App\Models\UserMonthlyPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveMonthlyLeaderboard extends Command
{
    protected $signature = 'leaderboard:archive-month';

    protected $description = 'Archive the previous monthly leaderboard and clear its active totals';

    public function handle(): int
    {
        $period = now()->subMonthNoOverflow();
        $archived = 0;

        DB::transaction(function () use ($period, &$archived): void {
            UserMonthlyPoint::query()
                ->where('month', $period->month)
                ->where('year', $period->year)
                ->orderByDesc('points')
                ->orderBy('user_id')
                ->get()
                ->each(function (UserMonthlyPoint $entry, int $index) use ($period, &$archived): void {
                    MonthlyLeaderboard::query()->updateOrCreate(
                        [
                            'user_id' => $entry->user_id,
                            'month' => $period->month,
                            'year' => $period->year,
                        ],
                        [
                            'points' => $entry->points,
                            'rank' => $index + 1,
                        ]
                    );

                    $archived++;
                });

            UserMonthlyPoint::query()
                ->where('month', $period->month)
                ->where('year', $period->year)
                ->delete();
        });

        $this->info(
            "Archived {$archived} leaderboard entries for {$period->format('F Y')}."
        );

        return self::SUCCESS;
    }
}

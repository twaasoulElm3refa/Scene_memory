<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ArchiveMonthlyLeaderboard extends Command
{
    protected $signature = 'leaderboard:archive-month';

    protected $description = 'Retired: monthly aggregates and archives are preserved as legacy history';

    public function handle(): int
    {
        $this->warn('Monthly archiving is retired. Use leaderboard:archive-weekly. Legacy records are unchanged.');

        return self::SUCCESS;
    }
}

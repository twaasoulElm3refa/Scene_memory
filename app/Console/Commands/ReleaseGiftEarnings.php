<?php

namespace App\Console\Commands;

use App\Services\GiftEarningsService;
use Illuminate\Console\Command;

class ReleaseGiftEarnings extends Command
{
    protected $signature = 'gifts:release-earnings {--limit=500}';

    protected $description = 'Release eligible held gift earnings to the dedicated creator ledger';

    public function handle(GiftEarningsService $earnings): int
    {
        $this->info('Released '.$earnings->release(max(1, min((int) $this->option('limit'), 5000))).' gift earnings.');

        return self::SUCCESS;
    }
}

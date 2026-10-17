<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile --limit=100 --age=10')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('gifts:release-earnings --limit=500')->hourly()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=1200')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('leaderboard:archive-weekly')
    ->weeklyOn(6, config('competition.archive_time', '00:05'))
    ->timezone(config('competition.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

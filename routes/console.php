<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:cloudinary-usage', function (\App\Services\UnifiedMediaStorage $storage) {
    if (! $storage->enabled('image') && ! $storage->enabled('video')) {
        $this->info('Cloudinary media is disabled.');
        return;
    }
    try {
        $usage = $storage->usage();
        $this->info("Cloudinary credits: {$usage['used']} / {$usage['limit']} ({$usage['percent']}%).");
        if ($usage['percent'] >= config('cloudinary_media.quota_warning_percent')) {
            \Log::warning('cloudinary_credit_warning', $usage);
        }
    } catch (\Throwable $e) {
        \Log::warning('cloudinary_usage_unavailable', ['exception_category' => get_class($e)]);
        $this->error('Cloudinary usage is unavailable.');
    }
})->purpose('Check cached Cloudinary credit usage');

Schedule::command('media:cloudinary-usage')->daily()->withoutOverlapping();

Schedule::command('media:backup-originals --limit=25')->daily()
    ->when(fn () => app(\App\Services\IndependentMediaBackup::class)->configured())
    ->withoutOverlapping(60);

Schedule::command('media:cleanup-originals --limit=25')->daily()
    ->when(fn () => config('cloudinary_media.temp_original_cleanup_enabled'))
    ->withoutOverlapping(60);

Schedule::command('payments:reconcile --limit=100 --age=10')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command('gifts:release-earnings --limit=500')->hourly()->withoutOverlapping();

Schedule::command('queue:work --stop-when-empty --tries=3 --timeout=1200 --max-time=1500')
    ->everyThirtySeconds()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('leaderboard:archive-weekly')
    ->weeklyOn(6, config('competition.archive_time', '00:05'))
    ->timezone(config('competition.timezone', 'Asia/Riyadh'))
    ->withoutOverlapping();

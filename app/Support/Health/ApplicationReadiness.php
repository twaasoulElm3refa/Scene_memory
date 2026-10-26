<?php

namespace App\Support\Health;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Makes GET /up a readiness check: it answers 500 when the database or the cache
 * store cannot serve requests. Messages stay generic; the cause is reported to
 * the log by the framework.
 */
class ApplicationReadiness
{
    public function handle(DiagnosingHealth $event): void
    {
        try {
            DB::connection()->select('SELECT 1');
        } catch (Throwable $exception) {
            throw new RuntimeException('Database is not reachable.', 0, $exception);
        }

        $key = 'health:probe:'.Str::random(20);
        try {
            Cache::put($key, 'ok', 30);
            $value = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $exception) {
            throw new RuntimeException('Cache store is not reachable.', 0, $exception);
        }
        if ($value !== 'ok') {
            throw new RuntimeException('Cache store did not return a written value.');
        }

        if (config('cache.require_tags') && ! Cache::supportsTags()) {
            throw new RuntimeException('Cache store does not support tags.');
        }
    }
}
